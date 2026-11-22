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
 * Collect functionality.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'));
if (!is_object($payload) || !confirm_sesskey($payload->sesskey ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$cmid = clean_param($payload->cmid ?? 0, PARAM_INT);
$attemptid = clean_param($payload->attemptid ?? 0, PARAM_INT);
if (!$cmid || !$attemptid || !local_examtelemetry_cmid_enabled($cmid)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$sql = "SELECT qa.id, qa.userid, qa.quiz, q.course, cm.id AS cmid
          FROM {quiz_attempts} qa
          JOIN {quiz} q ON q.id = qa.quiz
          JOIN {course_modules} cm ON cm.instance = q.id
          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
         WHERE qa.id = :attemptid AND qa.userid = :userid AND cm.id = :cmid";
$attempt = $DB->get_record_sql($sql, [
    'modname' => 'quiz', 'attemptid' => $attemptid, 'userid' => $USER->id, 'cmid' => $cmid,
]);
if (!$attempt) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$events = isset($payload->events) && is_array($payload->events) ? $payload->events : [$payload];
$batchlimit = 25;
if (count($events) > $batchlimit) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'batchlimit']);
    exit;
}

// Per-attempt rolling limit protects the endpoint while leaving ample room for diagnostics.
$ratelimit = max(20, (int)(get_config('local_examtelemetry', 'ratelimit') ?: 120));
$recent = $DB->count_records_select(
    'local_examtelemetry_event',
    'attemptid = :attemptid AND timecreated >= :since',
    ['attemptid' => $attemptid, 'since' => time() - MINSECS]
);
if ($recent >= $ratelimit) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode(['ok' => false, 'error' => 'ratelimit']);
    exit;
}

$allowedtypes = [
    'telemetry_start', 'finish_attempt_clicked', 'confirmation_dialog_opened',
    'final_submission_confirmed', 'long_task', 'js_error', 'unhandled_rejection',
    'offline', 'online', 'page_hidden', 'page_visible', 'navigation_timing',
    'transition_timing', 'event_loop_lag', 'resource_load_error', 'http_failure',
    'csp_violation', 'integration_failure', 'network_change',
];
$inserted = 0;
$duplicates = 0;
foreach (array_slice($events, 0, max(0, $ratelimit - $recent)) as $event) {
    if (!is_object($event)) {
        continue;
    }
    $eventtype = clean_param($event->eventtype ?? '', PARAM_ALPHANUMEXT);
    $pagekind = mb_substr(clean_param($event->pagekind ?? ($payload->pagekind ?? 'unknown'), PARAM_ALPHANUMEXT), 0, 20);
    if (!in_array($eventtype, $allowedtypes, true)) {
        continue;
    }
    $details = [];
    if (isset($event->details) && is_object($event->details)) {
        foreach ((array)$event->details as $key => $value) {
            $key = clean_param((string)$key, PARAM_ALPHANUMEXT);
            if (is_scalar($value) || $value === null) {
                $details[$key] = is_string($value)
                    ? mb_substr(clean_param($value, PARAM_TEXT), 0, 500) : $value;
            }
        }
    }
    $timeclient = isset($event->timeclient) ? clean_param($event->timeclient, PARAM_INT) : null;
    $clientid = mb_substr(clean_param($event->clientid ?? '', PARAM_ALPHANUMEXT), 0, 64);
    $eventhash = hash('sha256', implode(':', [
        $USER->id, $attemptid, $eventtype, $clientid ?: (string)$timeclient,
        isset($event->durationms) ? round((float)$event->durationms, 3) : '',
    ]));
    if ($DB->record_exists('local_examtelemetry_event', ['eventhash' => $eventhash])) {
        $duplicates++;
        continue;
    }
    $record = (object)[
        'userid' => $USER->id, 'courseid' => $attempt->course, 'cmid' => $cmid,
        'quizid' => $attempt->quiz, 'attemptid' => $attemptid, 'eventtype' => $eventtype,
        'pagekind' => $pagekind,
        'durationms' => isset($event->durationms) ? round((float)$event->durationms, 3) : null,
        'eventdata' => json_encode($details, JSON_UNESCAPED_SLASHES),
        'useragent' => mb_substr(clean_param($_SERVER['HTTP_USER_AGENT'] ?? '', PARAM_TEXT), 0, 1000),
        'timeclient' => $timeclient, 'eventhash' => $eventhash, 'timecreated' => time(),
    ];
    try {
        $DB->insert_record('local_examtelemetry_event', $record);
        $inserted++;
    } catch (dml_write_exception $exception) {
        // A concurrent retry may win the unique event-hash insert.
        $duplicates++;
    }
}
echo json_encode(['ok' => true, 'inserted' => $inserted, 'duplicates' => $duplicates]);
