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

/**
 * Lib functionality.
 *
 * @package    local_examtelemetry
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Load telemetry only on supported quiz attempt and summary pages.
 */
function local_examtelemetry_require_js(): void {
    global $PAGE, $USER;

    if (!get_config('local_examtelemetry', 'enabled') || !isloggedin() || isguestuser()) {
        return;
    }
    if (!\local_examtelemetry\local\license::is_valid()) {
        return;
    }

    $path = $PAGE->url->get_path();
    $supported = [
        '/mod/quiz/attempt.php' => 'attempt',
        '/mod/quiz/summary.php' => 'summary',
    ];
    if (!isset($supported[$path])) {
        return;
    }

    $cmid = optional_param('cmid', 0, PARAM_INT);
    $attemptid = optional_param('attempt', 0, PARAM_INT);
    if (!$cmid || !$attemptid || !local_examtelemetry_cmid_enabled($cmid)) {
        return;
    }

    $PAGE->requires->js_call_amd('local_examtelemetry/telemetry', 'init', [[
        'endpoint' => (new moodle_url('/local/examtelemetry/collect.php'))->out(false),
        'sesskey' => sesskey(),
        'cmid' => $cmid,
        'attemptid' => $attemptid,
        'pagekind' => $supported[$path],
        'longtaskthreshold' => max(50, (int)get_config('local_examtelemetry', 'longtaskthreshold')),
        'delaythreshold' => max(1000, (int)get_config('local_examtelemetry', 'delaythreshold')),
    ]]);
}

/**
 * Check whether collection is enabled and licensed.
 *
 * @return bool Whether collection is allowed.
 */
function local_examtelemetry_collection_allowed(): bool {
    return (bool)get_config('local_examtelemetry', 'enabled') && \local_examtelemetry\local\license::is_valid();
}

/**
 * Clean and limit event details.
 *
 * @param object|array $details Event details.
 * @return array Sanitised details.
 */
function local_examtelemetry_clean_details(object|array $details): array {
    $cleaned = [];
    foreach ((array)$details as $key => $value) {
        $key = clean_param((string)$key, PARAM_ALPHANUMEXT);
        if (count($cleaned) >= 20) {
            break;
        }
        if (is_scalar($value) || $value === null) {
            $candidate = $cleaned;
            $candidate[$key] = is_string($value) ? mb_substr(clean_param($value, PARAM_TEXT), 0, 500) : $value;
            $encoded = json_encode($candidate, JSON_UNESCAPED_SLASHES);
            if ($encoded !== false && strlen($encoded) <= 4096) {
                $cleaned = $candidate;
            }
        }
    }
    return $cleaned;
}

/**
 * Check whether collection is enabled for a course module.
 *
 * @param int $cmid Course-module ID.
 * @return bool
 */
function local_examtelemetry_cmid_enabled(int $cmid): bool {
    $configured = trim((string)get_config('local_examtelemetry', 'enabledcmids'));
    if ($configured === '') {
        return true;
    }
    $ids = array_filter(array_map('intval', preg_split('/[\s,;]+/', $configured)));
    return in_array($cmid, $ids, true);
}

/**
 * Format a millisecond value for the report ("—" when unknown).
 *
 * @param float|int|string|null $value Milliseconds or null.
 * @return string
 */
function local_examtelemetry_ms($value): string {
    return $value === null ? '—' : get_string('msvalue', 'local_examtelemetry', (string) round((float) $value));
}

/**
 * Translated strings for a list of keys sharing a prefix, e.g. prefix 'th' and key 'time' → 'th:time'.
 *
 * @param string $prefix Key prefix.
 * @param string[] $keys Key suffixes.
 * @return string[] Translated strings in the same order.
 */
function local_examtelemetry_strings(string $prefix, array $keys): array {
    return array_map(static function (string $key) use ($prefix): string {
        return get_string($prefix . ':' . $key, 'local_examtelemetry');
    }, $keys);
}

/**
 * Select options (value => translated label) for a list of keys sharing a prefix.
 *
 * @param string $prefix Key prefix.
 * @param string[] $keys Option values.
 * @return string[] value => label.
 */
function local_examtelemetry_options(string $prefix, array $keys): array {
    return array_combine($keys, local_examtelemetry_strings($prefix, $keys));
}

/**
 * Translated label of an event type; unknown types fall back to the raw token.
 *
 * @param string $eventtype Event type token.
 * @return string
 */
function local_examtelemetry_event_label(string $eventtype): string {
    $manager = get_string_manager();
    return $manager->string_exists('event:' . $eventtype, 'local_examtelemetry')
        ? get_string('event:' . $eventtype, 'local_examtelemetry')
        : s(str_replace('_', ' ', ucfirst($eventtype)));
}

/**
 * Translated label of a classification; unknown values fall back to the raw token.
 *
 * @param string $classification Classification token.
 * @return string
 */
function local_examtelemetry_classification_label(string $classification): string {
    return get_string_manager()->string_exists('classification:' . $classification, 'local_examtelemetry')
        ? get_string('classification:' . $classification, 'local_examtelemetry')
        : s(str_replace('_', ' ', ucfirst($classification)));
}

/**
 * Translated label of a severity.
 *
 * @param string $severity Severity token.
 * @return string
 */
function local_examtelemetry_severity_label(string $severity): string {
    return get_string_manager()->string_exists('severity:' . $severity, 'local_examtelemetry')
        ? get_string('severity:' . $severity, 'local_examtelemetry') : s($severity);
}

/**
 * Translated label of an incident review status.
 *
 * @param string $status Status token.
 * @return string
 */
function local_examtelemetry_status_label(string $status): string {
    return get_string_manager()->string_exists('status:' . $status, 'local_examtelemetry')
        ? get_string('status:' . $status, 'local_examtelemetry') : s($status);
}
