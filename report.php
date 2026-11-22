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
 * Report functionality.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
require_capability('local/examtelemetry:viewreports', $context);

$detail = optional_param('attemptid', 0, PARAM_INT);
$view = optional_param('view', 'incidents', PARAM_ALPHA);
$classification = optional_param('classification', '', PARAM_ALPHANUMEXT);
$severity = optional_param('severity', '', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$student = trim(optional_param('student', '', PARAM_TEXT));
$browser = trim(optional_param('browser', '', PARAM_ALPHANUMEXT));
$os = trim(optional_param('os', '', PARAM_ALPHANUMEXT));
$status = trim(optional_param('status', '', PARAM_ALPHANUMEXT));
$responsibility = trim(optional_param('responsibility', '', PARAM_ALPHANUMEXT));
$datefrom = optional_param('datefrom', '', PARAM_TEXT);
$dateto = optional_param('dateto', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);

if (data_submitted() && optional_param('updatesubmit', 0, PARAM_BOOL)) {
    require_sesskey();
    require_capability('local/examtelemetry:manageincidents', $context);
    $incidentid = required_param('incidentid', PARAM_INT);
    $newstatus = required_param('incidentstatus', PARAM_ALPHANUMEXT);
    if (!in_array($newstatus, ['open', 'acknowledged', 'resolved'], true)) {
        throw new moodle_exception('invalidparameter');
    }
    $DB->set_field('local_examtelemetry_inc', 'status', $newstatus, ['id' => $incidentid]);
    $DB->set_field(
        'local_examtelemetry_inc',
        'notes',
        optional_param('notes', '', PARAM_TEXT),
        ['id' => $incidentid]
    );
    redirect(new moodle_url('/local/examtelemetry/report.php', ['attemptid' => $detail]));
}

$PAGE->set_context($context);
$pageurlparams = [];
foreach (
    [
    'attemptid' => $detail, 'view' => $view, 'classification' => $classification,
    'severity' => $severity, 'courseid' => $courseid, 'cmid' => $cmid,
    'student' => $student, 'browser' => $browser, 'os' => $os, 'status' => $status,
    'responsibility' => $responsibility,
    'datefrom' => $datefrom, 'dateto' => $dateto, 'page' => $page,
    ] as $name => $value
) {
    if ($value !== '' && $value !== 0 && !($name === 'view' && $value === 'incidents')) {
        $pageurlparams[$name] = $value;
    }
}
$PAGE->set_url(new moodle_url('/local/examtelemetry/report.php', $pageurlparams));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('report', 'local_examtelemetry'));
$PAGE->set_heading(get_string('report', 'local_examtelemetry'));
$PAGE->navbar->add(get_string('reports'), new moodle_url('/admin/category.php', ['category' => 'reports']));
$PAGE->navbar->add(
    get_string('report', 'local_examtelemetry'),
    new moodle_url('/local/examtelemetry/report.php')
);
if ($detail) {
    $PAGE->navbar->add(get_string('attemptinvestigation', 'local_examtelemetry'));
}
$PAGE->requires->css('/local/examtelemetry/styles.css');

/**
 * Identifies the browser and operating system.
 *
 * @param string $ua User agent.
 * @return array Browser and operating system.
 */
function local_examtelemetry_device(string $ua): array {
    $browsers = ['Edge' => 'Edg/', 'Firefox' => 'Firefox/', 'Chrome' => 'Chrome/', 'Safari' => 'Safari/'];
    $oses = ['Windows' => 'Windows', 'Android' => 'Android', 'iOS' => 'iPhone', 'macOS' => 'Mac OS', 'Linux' => 'Linux'];
    $browser = 'Other';
    $os = 'Other';
    foreach ($browsers as $name => $needle) {
        if (stripos($ua, $needle) !== false) {
            $browser = $name;
            break;
        }
    }
    foreach ($oses as $name => $needle) {
        if (stripos($ua, $needle) !== false) {
            $os = $name;
            break;
        }
    }
    return [$browser, $os];
}

if ($detail) {
    $sql = "SELECT i.*, u.firstname, u.lastname, u.username, u.idnumber, q.name AS quizname,
                   c.fullname AS coursename, qa.state AS attemptstate
              FROM {local_examtelemetry_inc} i
              JOIN {user} u ON u.id = i.userid
              JOIN {quiz} q ON q.id = i.quizid
              JOIN {course} c ON c.id = i.courseid
              JOIN {quiz_attempts} qa ON qa.id = i.attemptid
             WHERE i.attemptid = :attemptid";
    $incident = $DB->get_record_sql($sql, ['attemptid' => $detail], MUST_EXIST);
    $events = $DB->get_records('local_examtelemetry_event', ['attemptid' => $detail], 'timecreated, id');
    $firstevent = reset($events);
    [$browsername, $osname] = local_examtelemetry_device($firstevent ? (string)$firstevent->useragent : '');
    echo $OUTPUT->header();
    echo html_writer::start_div('examtelemetry-navigation');
    echo html_writer::link(
        new moodle_url('/local/examtelemetry/report.php'),
        '← ' . get_string('backtoincidents', 'local_examtelemetry'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::link(
        new moodle_url('/admin/category.php', ['category' => 'reports']),
        get_string('backtoadminreports', 'local_examtelemetry'),
        ['class' => 'btn btn-outline-secondary']
    );
    echo html_writer::end_div();
    echo html_writer::tag('h2', s($incident->quizname) . ' — ' . fullname($incident));
    echo html_writer::start_div('examtelemetry-detail-grid');
    $isinprogressnormal = $incident->attemptstate === 'inprogress' && $incident->classification === 'normal';
    $conclusion = $isinprogressnormal
        ? get_string('monitoringnoincident', 'local_examtelemetry')
        : s($incident->classification) . ' (' . s($incident->severity) . ')';
    $evidence = $isinprogressnormal
        ? get_string('monitoringevidence', 'local_examtelemetry')
        : s($incident->summary);
    $facts = [
        'Student' => fullname($incident) . ($incident->idnumber ? ' (' . s($incident->idnumber) . ')' : ''),
        'Course / quiz' => s($incident->coursename) . ' / ' . s($incident->quizname),
        'Attempt / Moodle state' => $incident->attemptid . ' / ' . s($incident->attemptstate),
        'Browser / OS' => $browsername . ' / ' . $osname,
        'Automatic conclusion' => $conclusion,
        'Likely responsible area' => get_string(
            'responsibility:' . $incident->responsibility,
            'local_examtelemetry'
        ),
        'Supporting evidence' => $evidence,
        'Time to first byte' => $incident->serverwaitms === null ? '—' : round($incident->serverwaitms) . ' ms',
        'Apache processing' => $incident->apachems === null ? '—' : round($incident->apachems) . ' ms',
        'Longest browser block' => $incident->longesttaskms === null ? '—' : round($incident->longesttaskms) . ' ms',
        'JavaScript / offline events' => $incident->jserrors . ' / ' . $incident->offlineevents,
        'Resource / request failures' => $incident->resourceerrors . ' / ' . $incident->requestfailures,
        'Integration / CSP failures' => $incident->integrationfailures . ' / ' . $incident->cspviolations,
    ];
    foreach ($facts as $label => $value) {
        echo html_writer::div(html_writer::tag('strong', $label) . html_writer::div($value), 'examtelemetry-fact');
    }
    echo html_writer::end_div();
    echo html_writer::tag('h3', 'Client timeline');
    $labels = [
        'telemetry_start' => 'Telemetry started', 'navigation_timing' => 'Quiz loaded',
        'finish_attempt_clicked' => 'Finish attempt clicked',
        'confirmation_dialog_opened' => 'Confirmation dialog opened',
        'final_submission_confirmed' => 'Final submission confirmed',
        'page_hidden' => 'Page hidden', 'page_visible' => 'Page visible',
        'transition_timing' => 'Summary displayed', 'long_task' => 'Browser main thread blocked',
        'js_error' => 'JavaScript error', 'unhandled_rejection' => 'Unhandled JavaScript rejection',
        'offline' => 'Network offline', 'online' => 'Network online',
    ];
    echo html_writer::start_tag('ol', ['class' => 'examtelemetry-timeline']);
    foreach ($events as $event) {
        $label = $labels[$event->eventtype] ?? str_replace('_', ' ', ucfirst($event->eventtype));
        $duration = $event->durationms === null ? '' : ' — ' . round($event->durationms) . ' ms';
        $details = json_decode($event->eventdata ?? '{}', true) ?: [];
        echo html_writer::tag('li', html_writer::tag('time', userdate($event->timeclient ?: $event->timecreated, '%H:%M:%S')) .
            html_writer::tag('strong', s($label)) . s($duration) .
            ($details ? html_writer::div(s(json_encode($details, JSON_UNESCAPED_SLASHES)), 'text-muted small') : ''));
    }
    echo html_writer::end_tag('ol');
    if (
        $incident->classification !== 'normal' &&
            has_capability('local/examtelemetry:manageincidents', $context)
    ) {
        echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'examtelemetry-notes']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attemptid', 'value' => $detail]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'incidentid', 'value' => $incident->id]);
        echo html_writer::label(get_string('incidentstatus', 'local_examtelemetry'), 'incidentstatus');
        echo html_writer::select(
            ['open' => 'Open', 'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved'],
            'incidentstatus',
            $incident->status,
            false
        );
        echo html_writer::label(get_string('administratornotes', 'local_examtelemetry'), 'notes');
        echo html_writer::tag('textarea', s($incident->notes ?? ''), ['name' => 'notes', 'id' => 'notes', 'rows' => 4]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'updatesubmit', 'value' => 1,
            'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
    echo $OUTPUT->footer();
    exit;
}

$conditions = [];
$params = [];
if ($view === 'incidents') {
    $conditions[] = 'i.classification <> :normalview';
    $params['normalview'] = 'normal';
} else if ($view === 'healthy') {
    $conditions[] = 'i.classification = :normalview';
    $params['normalview'] = 'normal';
}
foreach (
    [
    'classification' => $classification, 'severity' => $severity, 'status' => $status,
    'responsibility' => $responsibility,
    ] as $field => $value
) {
    if ($value !== '') {
        $conditions[] = "i.$field = :$field";
        $params[$field] = $value;
    }
}
if ($courseid) {
    $conditions[] = 'i.courseid = :courseid';
    $params['courseid'] = $courseid;
}
if ($cmid) {
    $conditions[] = 'i.cmid = :cmid';
    $params['cmid'] = $cmid;
}
if ($student !== '') {
    $conditions[] = $DB->sql_like(
        $DB->sql_concat('u.firstname', "' '", 'u.lastname', "' '", 'u.username', "' '", 'u.idnumber'),
        ':student',
        false
    );
    $params['student'] = '%' . $DB->sql_like_escape($student) . '%';
}
if ($datefrom && ($timestamp = strtotime($datefrom)) !== false) {
    $conditions[] = 'i.timelastevent >= :datefrom';
    $params['datefrom'] = $timestamp;
}
if ($dateto && ($timestamp = strtotime($dateto . ' +1 day')) !== false) {
    $conditions[] = 'i.timelastevent < :dateto';
    $params['dateto'] = $timestamp;
}
$devicepatterns = [
    'browser' => ['chrome' => 'Chrome/', 'firefox' => 'Firefox/', 'edge' => 'Edg/', 'safari' => 'Safari/'],
    'os' => ['windows' => 'Windows', 'macos' => 'Mac OS', 'linux' => 'Linux', 'android' => 'Android', 'ios' => 'iPhone'],
];
foreach (['browser' => $browser, 'os' => $os] as $field => $value) {
    if ($value !== '' && isset($devicepatterns[$field][strtolower($value)])) {
        $conditions[] = "EXISTS (SELECT 1 FROM {local_examtelemetry_event} de
                                  WHERE de.attemptid = i.attemptid AND " .
            $DB->sql_like('de.useragent', ":$field", false) . ')';
        $params[$field] = '%' . $devicepatterns[$field][strtolower($value)] . '%';
    }
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sqlfrom = "FROM {local_examtelemetry_inc} i
            JOIN {user} u ON u.id = i.userid
            JOIN {quiz} q ON q.id = i.quizid
            JOIN {course} c ON c.id = i.courseid
            JOIN {quiz_attempts} qa ON qa.id = i.attemptid $where";
$select = "SELECT i.*, u.firstname, u.lastname, u.username, u.idnumber,
                  q.name AS quizname, c.fullname AS coursename, qa.state AS attemptstate";

if ($export === 'csv') {
    $records = $DB->get_records_sql("$select $sqlfrom ORDER BY i.timemodified DESC", $params);
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename('exam-telemetry-' . date('Y-m-d'));
    $csv->add_data(['Time', 'Student', 'Student number', 'Course', 'Quiz', 'Attempt', 'Classification',
        'Severity', 'Status', 'Responsibility', 'Transition ms', 'Time to first byte ms', 'Apache ms', 'Render ms',
        'Longest task ms', 'JS errors', 'Offline events', 'Affected students', 'Summary']);
    foreach ($records as $record) {
        $csv->add_data([userdate($record->timemodified), fullname($record), $record->idnumber,
            $record->coursename, $record->quizname, $record->attemptid, $record->classification,
            $record->severity, $record->status, $record->responsibility, $record->transitionms, $record->serverwaitms,
            $record->apachems, $record->renderms, $record->longesttaskms, $record->jserrors,
            $record->offlineevents, $record->clustercount, $record->summary]);
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo html_writer::start_div('examtelemetry-navigation');
echo html_writer::link(
    new moodle_url('/my/'),
    get_string('backtodashboard', 'local_examtelemetry'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link(
    new moodle_url('/admin/category.php', ['category' => 'reports']),
    get_string('backtoadminreports', 'local_examtelemetry'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::end_div();
$stats = $DB->get_record_sql("SELECT COUNT(*) AS monitored,
    SUM(CASE WHEN classification = 'normal' THEN 1 ELSE 0 END) AS normalattempts,
    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical,
    SUM(CASE WHEN classification = 'browser_js_freeze' THEN 1 ELSE 0 END) AS freezes,
    SUM(CASE WHEN classification IN ('server_delay','server_or_network_delay') THEN 1 ELSE 0 END) AS serverdelays,
    SUM(jserrors) AS jserrors, SUM(offlineevents) AS networkevents FROM {local_examtelemetry_inc}");
echo html_writer::start_div('examtelemetry-cards');
foreach (
    [
    'Attempts monitored' => $stats->monitored, 'Normal attempts' => $stats->normalattempts,
    'Critical incidents' => $stats->critical, 'Browser freezes' => $stats->freezes,
    'Server delays' => $stats->serverdelays, 'JavaScript errors' => $stats->jserrors,
    'Network interruptions' => $stats->networkevents,
    ] as $label => $number
) {
    echo html_writer::div(html_writer::tag('span', (int)$number) . html_writer::div($label), 'examtelemetry-card');
}
echo html_writer::end_div();

$baseparams = $_GET;
echo html_writer::div(
    html_writer::link(new moodle_url('/local/examtelemetry/report.php', ['view' => 'incidents']), 'Incidents') . ' ' .
    html_writer::link(new moodle_url('/local/examtelemetry/report.php', ['view' => 'healthy']), 'Healthy attempts') . ' ' .
    html_writer::link(new moodle_url('/local/examtelemetry/report.php', ['view' => 'all']), 'All attempts'),
    'examtelemetry-views'
);
$courses = $DB->get_records_menu('course', null, 'fullname', 'id, fullname');
$quizzes = $DB->get_records_sql_menu("SELECT cm.id, " . $DB->sql_concat('c.shortname', "' — '", 'q.name') .
    " AS label FROM {course_modules} cm JOIN {modules} m ON m.id=cm.module AND m.name=:quiz
    JOIN {quiz} q ON q.id=cm.instance JOIN {course} c ON c.id=q.course ORDER BY c.shortname,q.name", ['quiz' => 'quiz']);
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'examtelemetry-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'view', 'value' => $view]);
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'datefrom', 'value' => $datefrom, 'aria-label' => 'Date from']);
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'dateto', 'value' => $dateto, 'aria-label' => 'Date to']);
echo html_writer::select(['' => 'All courses'] + $courses, 'courseid', $courseid, false);
echo html_writer::select(['' => 'All quizzes'] + $quizzes, 'cmid', $cmid, false, ['class' => 'custom-select']);
echo html_writer::empty_tag('input', ['name' => 'student', 'value' => $student, 'placeholder' => 'Student or number']);
echo html_writer::select(
    ['' => 'All classifications', 'normal' => 'Normal', 'browser_js_freeze' => 'Browser issue',
    'javascript_error' => 'JavaScript error', 'network_interruption' => 'Network issue',
    'server_delay' => 'Server issue', 'server_or_network_delay' => 'Server/network issue',
    'unexplained_transition_delay' => 'Transition delay', 'failed_final_submission' => 'Failed submission',
    'external_integration_failure' => 'External integration failure',
    'external_service_failure' => 'External service failure',
    'external_resource_failure' => 'External resource failure',
    'security_policy_failure' => 'Security policy failure',
    'moodle_request_failure' => 'Moodle request failure',
    'network_request_failure' => 'Network request failure',
    'resource_load_failure' => 'Resource load failure'],
    'classification',
    $classification,
    false
);
echo html_writer::select(['' => 'All severities', 'info' => 'Info', 'medium' => 'Medium',
    'high' => 'High', 'critical' => 'Critical'], 'severity', $severity, false);
echo html_writer::select(['' => 'All browsers', 'chrome' => 'Chrome', 'firefox' => 'Firefox',
    'edge' => 'Edge', 'safari' => 'Safari'], 'browser', $browser, false);
echo html_writer::select(['' => 'All operating systems', 'windows' => 'Windows', 'macos' => 'macOS',
    'linux' => 'Linux', 'android' => 'Android', 'ios' => 'iOS'], 'os', $os, false);
echo html_writer::select(['' => 'All statuses', 'open' => 'Open', 'acknowledged' => 'Acknowledged',
    'resolved' => 'Resolved', 'not_applicable' => 'Healthy (not an incident)'], 'status', $status, false);
echo html_writer::select([
    '' => 'All responsible areas',
    'student_device_browser' => 'Student device/browser',
    'student_network' => 'Student network',
    'moodle_server' => 'Moodle/server',
    'external_service' => 'External service',
    'exam_configuration' => 'Exam configuration',
    'unknown_shared' => 'Server or network',
    'unknown' => 'Unknown',
], 'responsibility', $responsibility, false);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Filter', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

$total = $DB->count_records_sql("SELECT COUNT(1) $sqlfrom", $params);
$perpage = 50;
$records = $DB->get_records_sql(
    "$select $sqlfrom ORDER BY i.timemodified DESC",
    $params,
    $page * $perpage,
    $perpage
);
$baseparams['export'] = 'csv';
echo html_writer::div(html_writer::link(
    new moodle_url('/local/examtelemetry/report.php', $baseparams),
    'Export filtered CSV',
    ['class' => 'btn btn-secondary']
), 'mb-3');
if (!$records) {
    echo $OUTPUT->notification(get_string('noincidents', 'local_examtelemetry'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable examtelemetry-table';
    $table->head = ['Time', 'Student', 'Quiz', 'Attempt', 'Classification', 'Severity', 'Status',
        'Responsible area', 'Transition', 'Time to first byte', 'Apache', 'Affected', 'Conclusion'];
    foreach ($records as $record) {
        $detailurl = new moodle_url('/local/examtelemetry/report.php', ['attemptid' => $record->attemptid]);
        $classificationlabel = $record->classification === 'normal' && $record->attemptstate === 'inprogress'
            ? get_string('monitoring', 'local_examtelemetry')
            : str_replace('_', ' ', ucfirst($record->classification));
        $badge = html_writer::span(
            $classificationlabel,
            'examtelemetry-badge class-' . s($record->classification)
        );
        $table->data[] = [userdate($record->timemodified), fullname($record),
            s($record->quizname), html_writer::link($detailurl, '#' . $record->attemptid),
            $badge, html_writer::span(s($record->severity), 'severity-' . s($record->severity)),
            $record->classification === 'normal'
                ? html_writer::span(get_string(
                    $record->attemptstate === 'inprogress' ? 'monitoring' : 'healthystatus',
                    'local_examtelemetry'
                ), 'status-healthy')
                : s($record->status),
            get_string('responsibility:' . $record->responsibility, 'local_examtelemetry'),
            $record->transitionms === null ? '—' : round($record->transitionms) . ' ms',
            $record->serverwaitms === null ? '—' : round($record->serverwaitms) . ' ms',
            $record->apachems === null ? '—' : round($record->apachems) . ' ms',
            (int)$record->clustercount, s($record->summary)];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
}
echo $OUTPUT->footer();
