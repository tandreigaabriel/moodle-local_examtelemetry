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
require_once(__DIR__ . '/lib.php');
require_login();
$context = context_system::instance();
require_capability('local/examtelemetry:viewreports', $context);
\local_examtelemetry\local\license::require_valid();

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

/**
 * Identifies the browser and operating system.
 *
 * @param string $ua User agent.
 * @return array Browser and operating system.
 */
function local_examtelemetry_device(string $ua): array {
    $browsers = ['Edge' => 'Edg/', 'Firefox' => 'Firefox/', 'Chrome' => 'Chrome/', 'Safari' => 'Safari/'];
    $oses = ['Windows' => 'Windows', 'Android' => 'Android', 'iOS' => 'iPhone', 'macOS' => 'Mac OS', 'Linux' => 'Linux'];
    $browser = get_string('other', 'local_examtelemetry');
    $os = get_string('other', 'local_examtelemetry');
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

$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

if ($detail) {
    $sql = "SELECT i.*, $namefields, u.username, u.idnumber, q.name AS quizname,
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
        : local_examtelemetry_classification_label($incident->classification) .
            ' (' . local_examtelemetry_severity_label($incident->severity) . ')';
    $evidence = $isinprogressnormal
        ? get_string('monitoringevidence', 'local_examtelemetry')
        : s($incident->summary);
    $facts = [
        'fact:student' => fullname($incident) . ($incident->idnumber ? ' (' . s($incident->idnumber) . ')' : ''),
        'fact:coursequiz' => s($incident->coursename) . ' / ' . s($incident->quizname),
        'fact:attemptstate' => $incident->attemptid . ' / ' . s($incident->attemptstate),
        'fact:browseros' => s($browsername) . ' / ' . s($osname),
        'fact:conclusion' => $conclusion,
        'fact:responsibility' => get_string(
            'responsibility:' . $incident->responsibility,
            'local_examtelemetry'
        ),
        'fact:evidence' => $evidence,
        'fact:ttfb' => local_examtelemetry_ms($incident->serverwaitms),
        'fact:apache' => local_examtelemetry_ms($incident->apachems),
        'fact:longestblock' => local_examtelemetry_ms($incident->longesttaskms),
        'fact:jsoffline' => $incident->jserrors . ' / ' . $incident->offlineevents,
        'fact:resourcerequest' => $incident->resourceerrors . ' / ' . $incident->requestfailures,
        'fact:integrationcsp' => $incident->integrationfailures . ' / ' . $incident->cspviolations,
    ];
    foreach ($facts as $key => $value) {
        echo html_writer::div(
            html_writer::tag('strong', get_string($key, 'local_examtelemetry')) . html_writer::div($value),
            'examtelemetry-fact'
        );
    }
    echo html_writer::end_div();
    echo html_writer::tag('h3', get_string('clienttimeline', 'local_examtelemetry'));
    echo html_writer::start_tag('ol', ['class' => 'examtelemetry-timeline']);
    foreach ($events as $event) {
        $label = local_examtelemetry_event_label($event->eventtype);
        $duration = $event->durationms === null ? '' : ' — ' . local_examtelemetry_ms($event->durationms);
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
            local_examtelemetry_options('status', ['open', 'acknowledged', 'resolved']),
            'incidentstatus',
            $incident->status,
            false
        );
        echo html_writer::label(get_string('administratornotes', 'local_examtelemetry'), 'notes');
        echo html_writer::tag('textarea', s($incident->notes ?? ''), ['name' => 'notes', 'id' => 'notes', 'rows' => 4]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'updatesubmit', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('saveincident', 'local_examtelemetry'),
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
$select = "SELECT i.*, $namefields, u.username, u.idnumber,
                  q.name AS quizname, c.fullname AS coursename, qa.state AS attemptstate";

if ($export === 'csv') {
    $records = $DB->get_records_sql("$select $sqlfrom ORDER BY i.timemodified DESC", $params);
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename('exam-telemetry-' . date('Y-m-d'));
    $csv->add_data(local_examtelemetry_strings('csv', ['time', 'student', 'studentnumber', 'course', 'quiz', 'attempt',
        'classification', 'severity', 'status', 'responsibility', 'transitionms', 'ttfbms', 'apachems', 'renderms',
        'longesttaskms', 'jserrors', 'offlineevents', 'affected', 'summary']));
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
    'stat:monitored' => $stats->monitored, 'stat:normal' => $stats->normalattempts,
    'stat:critical' => $stats->critical, 'stat:freezes' => $stats->freezes,
    'stat:serverdelays' => $stats->serverdelays, 'stat:jserrors' => $stats->jserrors,
    'stat:network' => $stats->networkevents,
    ] as $key => $number
) {
    echo html_writer::div(
        html_writer::tag('span', (int)$number) . html_writer::div(get_string($key, 'local_examtelemetry')),
        'examtelemetry-card'
    );
}
echo html_writer::end_div();

$baseparams = $_GET;
$viewlinks = [];
foreach (['incidents', 'healthy', 'all'] as $viewname) {
    $viewlinks[] = html_writer::link(
        new moodle_url('/local/examtelemetry/report.php', ['view' => $viewname]),
        get_string('view:' . $viewname, 'local_examtelemetry')
    );
}
echo html_writer::div(
    implode(' ', $viewlinks),
    'examtelemetry-views'
);
$courses = $DB->get_records_menu('course', null, 'fullname', 'id, fullname');
$quizzes = $DB->get_records_sql_menu("SELECT cm.id, " . $DB->sql_concat('c.shortname', "' — '", 'q.name') .
    " AS label FROM {course_modules} cm JOIN {modules} m ON m.id=cm.module AND m.name=:quiz
    JOIN {quiz} q ON q.id=cm.instance JOIN {course} c ON c.id=q.course ORDER BY c.shortname,q.name", ['quiz' => 'quiz']);
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'examtelemetry-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'view', 'value' => $view]);
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'datefrom', 'value' => $datefrom,
    'aria-label' => get_string('datefrom', 'local_examtelemetry')]);
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'dateto', 'value' => $dateto,
    'aria-label' => get_string('dateto', 'local_examtelemetry')]);
echo html_writer::select(['' => get_string('allcourses', 'local_examtelemetry')] + $courses, 'courseid', $courseid, false);
echo html_writer::select(
    ['' => get_string('allquizzes', 'local_examtelemetry')] + $quizzes,
    'cmid',
    $cmid,
    false,
    ['class' => 'custom-select']
);
echo html_writer::empty_tag('input', ['name' => 'student', 'value' => $student,
    'placeholder' => get_string('studentplaceholder', 'local_examtelemetry')]);
echo html_writer::select(
    ['' => get_string('allclassifications', 'local_examtelemetry')] + local_examtelemetry_options('classification', [
        'normal', 'browser_js_freeze', 'javascript_error', 'network_interruption', 'server_delay',
        'server_or_network_delay', 'unexplained_transition_delay', 'failed_final_submission',
        'external_integration_failure', 'external_service_failure', 'external_resource_failure',
        'security_policy_failure', 'moodle_request_failure', 'network_request_failure', 'resource_load_failure',
        'browser_render_delay',
    ]),
    'classification',
    $classification,
    false
);
echo html_writer::select(['' => get_string('allseverities', 'local_examtelemetry')] +
    local_examtelemetry_options('severity', ['info', 'medium', 'high', 'critical']), 'severity', $severity, false);
echo html_writer::select(['' => get_string('allbrowsers', 'local_examtelemetry'), 'chrome' => 'Chrome', 'firefox' => 'Firefox',
    'edge' => 'Edge', 'safari' => 'Safari'], 'browser', $browser, false);
echo html_writer::select(['' => get_string('alloperatingsystems', 'local_examtelemetry'), 'windows' => 'Windows',
    'macos' => 'macOS', 'linux' => 'Linux', 'android' => 'Android', 'ios' => 'iOS'], 'os', $os, false);
echo html_writer::select(['' => get_string('allstatuses', 'local_examtelemetry')] +
    local_examtelemetry_options('status', ['open', 'acknowledged', 'resolved', 'not_applicable']), 'status', $status, false);
echo html_writer::select(['' => get_string('allresponsibilities', 'local_examtelemetry')] +
    local_examtelemetry_options('responsibility', ['student_device_browser', 'student_network', 'moodle_server',
        'external_service', 'exam_configuration', 'unknown_shared', 'unknown']), 'responsibility', $responsibility, false);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter', 'local_examtelemetry'),
    'class' => 'btn btn-primary']);
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
    get_string('exportcsv', 'local_examtelemetry'),
    ['class' => 'btn btn-secondary']
), 'mb-3');
if (!$records) {
    echo $OUTPUT->notification(get_string('noincidents', 'local_examtelemetry'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable examtelemetry-table';
    $table->head = local_examtelemetry_strings('th', ['time', 'student', 'quiz', 'attempt', 'classification', 'severity',
        'status', 'responsibility', 'transition', 'ttfb', 'apache', 'affected', 'conclusion']);
    foreach ($records as $record) {
        $detailurl = new moodle_url('/local/examtelemetry/report.php', ['attemptid' => $record->attemptid]);
        $classificationlabel = $record->classification === 'normal' && $record->attemptstate === 'inprogress'
            ? get_string('monitoring', 'local_examtelemetry')
            : local_examtelemetry_classification_label($record->classification);
        $badge = html_writer::span(
            $classificationlabel,
            'examtelemetry-badge class-' . s($record->classification)
        );
        $table->data[] = [userdate($record->timemodified), fullname($record),
            s($record->quizname), html_writer::link($detailurl, '#' . $record->attemptid),
            $badge, html_writer::span(local_examtelemetry_severity_label($record->severity), 'severity-' . s($record->severity)),
            $record->classification === 'normal'
                ? html_writer::span(get_string(
                    $record->attemptstate === 'inprogress' ? 'monitoring' : 'healthystatus',
                    'local_examtelemetry'
                ), 'status-healthy')
                : local_examtelemetry_status_label($record->status),
            get_string('responsibility:' . $record->responsibility, 'local_examtelemetry'),
            local_examtelemetry_ms($record->transitionms),
            local_examtelemetry_ms($record->serverwaitms),
            local_examtelemetry_ms($record->apachems),
            (int)$record->clustercount, s($record->summary)];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
}
echo $OUTPUT->footer();
