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
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Load telemetry only on supported quiz attempt and summary pages.
 */
function local_examtelemetry_require_js(): void {
    global $PAGE, $USER;

    if (!get_config('local_examtelemetry', 'enabled') || !isloggedin() || isguestuser()) {
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
