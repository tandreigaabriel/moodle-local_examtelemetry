<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_examtelemetry;

/**
 * Incrementally ingest privacy-minimised Apache timing lines.
 *
 * @package    local_examtelemetry
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class apache_reader {
    /** Maximum bytes consumed by one cron execution. */
    private const MAX_BYTES = 1048576;

    /** Maximum complete lines consumed by one cron execution. */
    private const MAX_LINES = 5000;

    /**
     * Resume from the saved byte offset and upsert the longest duration per attempt.
     *
     * @return int Number of correlated lines ingested.
     */
    public static function ingest(): int {
        global $DB;

        $path = trim((string)get_config('local_examtelemetry', 'apachelogpath'));
        if ($path === '' || !is_readable($path) || !is_file($path)) {
            return 0;
        }
        clearstatcache(true, $path);
        $inode = @fileinode($path);
        $identity = hash('sha256', $path . ':' . ($inode ?: 'unknown'));
        $savedidentity = (string)get_config('local_examtelemetry', 'apachelogidentity');
        $offset = (int)get_config('local_examtelemetry', 'apachelogoffset');
        $size = (int)filesize($path);
        // A new path or a file smaller than the offset indicates first use or rotation.
        if ($savedidentity !== $identity || $offset < 0 || $offset > $size) {
            $offset = 0;
        }

        $handle = fopen($path, 'rb');
        if (!$handle || fseek($handle, $offset, SEEK_SET) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return 0;
        }
        $start = $offset;
        $lines = 0;
        $ingested = 0;
        while ($lines < self::MAX_LINES && ftell($handle) - $start < self::MAX_BYTES) {
            $linestart = ftell($handle);
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            // Do not advance past a line still being written by Apache.
            if (!str_ends_with($line, "\n")) {
                fseek($handle, $linestart, SEEK_SET);
                break;
            }
            $lines++;
            if (
                !preg_match('/duration_us=(\d+)/', $line, $duration) ||
                    !preg_match('/(?:attempt=|attempt%3D)(\d+)/i', $line, $attempt)
            ) {
                continue;
            }
            $attemptid = (int)$attempt[1];
            $milliseconds = (float)$duration[1] / 1000;
            $existing = $DB->get_record('local_examtelemetry_apache', ['attemptid' => $attemptid]);
            $now = time();
            if ($existing) {
                $existing->durationms = max((float)$existing->durationms, $milliseconds);
                $existing->timemodified = $now;
                $DB->update_record('local_examtelemetry_apache', $existing);
            } else {
                $DB->insert_record('local_examtelemetry_apache', (object)[
                    'attemptid' => $attemptid,
                    'durationms' => $milliseconds,
                    'timemodified' => $now,
                ]);
            }
            $ingested++;
        }
        $newoffset = ftell($handle);
        fclose($handle);
        set_config('apachelogidentity', $identity, 'local_examtelemetry');
        set_config('apachelogoffset', $newoffset, 'local_examtelemetry');
        return $ingested;
    }
}
