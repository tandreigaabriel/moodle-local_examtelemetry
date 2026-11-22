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
 * Local examtelemetry functionality.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Exam telemetry';
$string['privacy:metadata:event'] = 'Browser performance telemetry collected during selected quiz attempts.';
$string['privacy:metadata:event:userid'] = 'The user associated with the quiz attempt.';
$string['privacy:metadata:event:courseid'] = 'The course containing the quiz.';
$string['privacy:metadata:event:cmid'] = 'The quiz course-module ID.';
$string['privacy:metadata:event:quizid'] = 'The quiz activity ID.';
$string['privacy:metadata:event:attemptid'] = 'The quiz attempt ID.';
$string['privacy:metadata:event:eventtype'] = 'The type of browser performance event.';
$string['privacy:metadata:event:eventdata'] = 'Sanitised diagnostic details for the event.';
$string['privacy:metadata:event:useragent'] = 'The browser user-agent string.';
$string['privacy:metadata:event:timecreated'] = 'When Moodle received the event.';
$string['privacy:metadata:incident'] = 'An automatically classified performance incident.';
$string['privacy:metadata:incident:classification'] = 'The automatic incident classification.';
$string['privacy:metadata:incident:responsibility'] = 'The automatically inferred responsible area.';
$string['privacy:metadata:incident:summary'] = 'A generated diagnostic summary.';
$string['examtelemetry:viewreports'] = 'View exam telemetry reports';
$string['examtelemetry:manageincidents'] = 'Manage exam telemetry incidents';
$string['taskclassify'] = 'Classify exam telemetry incidents';
$string['taskcleanup'] = 'Delete expired exam telemetry';
$string['settingsenabled'] = 'Enable telemetry';
$string['settingsenabled_desc'] = 'Collect privacy-minimised browser performance telemetry on selected quiz pages.';
$string['enabledcmids'] = 'Enabled quiz course-module IDs';
$string['enabledcmids_desc'] = 'Comma-separated course-module IDs. Leave empty to collect from every quiz when telemetry is enabled.';
$string['longtaskthreshold'] = 'Long-task threshold';
$string['longtaskthreshold_desc'] = 'Minimum blocked main-thread duration in milliseconds.';
$string['delaythreshold'] = 'Delay threshold';
$string['delaythreshold_desc'] = 'Minimum transition, time-to-first-byte, or render duration in milliseconds before an incident is raised.';
$string['retentiondays'] = 'Retention period';
$string['retentiondays_desc'] = 'Automatically delete telemetry and incident records older than this number of days.';
$string['ratelimit'] = 'Events per attempt per minute';
$string['ratelimit_desc'] = 'Maximum accepted telemetry events for one attempt in a rolling minute.';
$string['apachelogpath'] = 'Apache quiz timing log';
$string['apachelogpath_desc'] = 'Optional absolute path to the focused Apache timing log. Leave empty to disable correlation.';
$string['classifierbatchsize'] = 'Classifier batch size';
$string['classifierbatchsize_desc'] = 'Maximum changed attempts processed by each five-minute classifier run.';
$string['report'] = 'Exam telemetry incidents';
$string['noincidents'] = 'No matching incidents were found.';
$string['attemptinvestigation'] = 'Attempt investigation';
$string['backtoincidents'] = 'Back to exam telemetry';
$string['backtoadminreports'] = 'All administration reports';
$string['backtodashboard'] = 'Back to dashboard';
$string['healthystatus'] = 'Healthy';
$string['incidentstatus'] = 'Incident review status';
$string['administratornotes'] = 'Administrator investigation notes';
$string['monitoring'] = 'Monitoring';
$string['monitoringnoincident'] = 'Monitoring — no incident detected so far';
$string['monitoringevidence'] = 'The attempt is still in progress. No configured incident signal has been detected so far; this is not a final conclusion.';
$string['responsibility:none'] = 'No incident';
$string['responsibility:student_device_browser'] = 'Student device/browser';
$string['responsibility:student_network'] = 'Student network';
$string['responsibility:moodle_server'] = 'Moodle/server';
$string['responsibility:external_service'] = 'External service';
$string['responsibility:exam_configuration'] = 'Exam configuration';
$string['responsibility:unknown_shared'] = 'Server or network';
$string['responsibility:unknown'] = 'Unknown';
$string['other'] = 'Other';
$string['fact:student'] = 'Student';
$string['fact:coursequiz'] = 'Course / quiz';
$string['fact:attemptstate'] = 'Attempt / Moodle state';
$string['fact:browseros'] = 'Browser / OS';
$string['fact:conclusion'] = 'Automatic conclusion';
$string['fact:responsibility'] = 'Likely responsible area';
$string['fact:evidence'] = 'Supporting evidence';
$string['fact:ttfb'] = 'Time to first byte';
$string['fact:apache'] = 'Apache processing';
$string['fact:longestblock'] = 'Longest browser block';
$string['fact:jsoffline'] = 'JavaScript / offline events';
$string['fact:resourcerequest'] = 'Resource / request failures';
$string['fact:integrationcsp'] = 'Integration / CSP failures';
$string['clienttimeline'] = 'Client timeline';
$string['event:telemetry_start'] = 'Telemetry started';
$string['event:navigation_timing'] = 'Quiz loaded';
$string['event:finish_attempt_clicked'] = 'Finish attempt clicked';
$string['event:confirmation_dialog_opened'] = 'Confirmation dialog opened';
$string['event:final_submission_confirmed'] = 'Final submission confirmed';
$string['event:page_hidden'] = 'Page hidden';
$string['event:page_visible'] = 'Page visible';
$string['event:transition_timing'] = 'Summary displayed';
$string['event:long_task'] = 'Browser main thread blocked';
$string['event:js_error'] = 'JavaScript error';
$string['event:unhandled_rejection'] = 'Unhandled JavaScript rejection';
$string['event:offline'] = 'Network offline';
$string['event:online'] = 'Network online';
$string['event:event_loop_lag'] = 'Browser event loop lag';
$string['event:resource_load_error'] = 'Resource failed to load';
$string['event:http_failure'] = 'HTTP request failed';
$string['event:csp_violation'] = 'Content Security Policy block';
$string['event:integration_failure'] = 'External integration failure';
$string['event:network_change'] = 'Network change';
$string['status:open'] = 'Open';
$string['status:acknowledged'] = 'Acknowledged';
$string['status:resolved'] = 'Resolved';
$string['status:not_applicable'] = 'Healthy (not an incident)';
$string['csv:time'] = 'Time';
$string['csv:student'] = 'Student';
$string['csv:studentnumber'] = 'Student number';
$string['csv:course'] = 'Course';
$string['csv:quiz'] = 'Quiz';
$string['csv:attempt'] = 'Attempt';
$string['csv:classification'] = 'Classification';
$string['csv:severity'] = 'Severity';
$string['csv:status'] = 'Status';
$string['csv:responsibility'] = 'Responsibility';
$string['csv:transitionms'] = 'Transition ms';
$string['csv:ttfbms'] = 'Time to first byte ms';
$string['csv:apachems'] = 'Apache ms';
$string['csv:renderms'] = 'Render ms';
$string['csv:longesttaskms'] = 'Longest task ms';
$string['csv:jserrors'] = 'JS errors';
$string['csv:offlineevents'] = 'Offline events';
$string['csv:affected'] = 'Affected students';
$string['csv:summary'] = 'Summary';
$string['stat:monitored'] = 'Attempts monitored';
$string['stat:normal'] = 'Normal attempts';
$string['stat:critical'] = 'Critical incidents';
$string['stat:freezes'] = 'Browser freezes';
$string['stat:serverdelays'] = 'Server delays';
$string['stat:jserrors'] = 'JavaScript errors';
$string['stat:network'] = 'Network interruptions';
$string['view:incidents'] = 'Incidents';
$string['view:healthy'] = 'Healthy attempts';
$string['view:all'] = 'All attempts';
$string['datefrom'] = 'Date from';
$string['dateto'] = 'Date to';
$string['allcourses'] = 'All courses';
$string['allquizzes'] = 'All quizzes';
$string['studentplaceholder'] = 'Student or number';
$string['allclassifications'] = 'All classifications';
$string['allseverities'] = 'All severities';
$string['allbrowsers'] = 'All browsers';
$string['alloperatingsystems'] = 'All operating systems';
$string['allstatuses'] = 'All statuses';
$string['allresponsibilities'] = 'All responsible areas';
$string['filter'] = 'Filter';
$string['exportcsv'] = 'Export filtered CSV';
$string['severity:info'] = 'Info';
$string['severity:medium'] = 'Medium';
$string['severity:high'] = 'High';
$string['severity:critical'] = 'Critical';
$string['classification:normal'] = 'Normal';
$string['classification:browser_js_freeze'] = 'Browser freeze';
$string['classification:javascript_error'] = 'JavaScript error';
$string['classification:network_interruption'] = 'Network interruption';
$string['classification:server_delay'] = 'Server delay';
$string['classification:server_or_network_delay'] = 'Server or network delay';
$string['classification:unexplained_transition_delay'] = 'Unexplained transition delay';
$string['classification:failed_final_submission'] = 'Failed final submission';
$string['classification:external_integration_failure'] = 'External integration failure';
$string['classification:external_service_failure'] = 'External service failure';
$string['classification:external_resource_failure'] = 'External resource failure';
$string['classification:security_policy_failure'] = 'Security policy failure';
$string['classification:moodle_request_failure'] = 'Moodle request failure';
$string['classification:network_request_failure'] = 'Network request failure';
$string['classification:resource_load_failure'] = 'Resource load failure';
$string['classification:browser_render_delay'] = 'Browser render delay';
$string['msvalue'] = '{$a} ms';
$string['th:time'] = 'Time';
$string['th:student'] = 'Student';
$string['th:quiz'] = 'Quiz';
$string['th:attempt'] = 'Attempt';
$string['th:classification'] = 'Classification';
$string['th:severity'] = 'Severity';
$string['th:status'] = 'Status';
$string['th:responsibility'] = 'Responsible area';
$string['th:transition'] = 'Transition';
$string['th:ttfb'] = 'Time to first byte';
$string['th:apache'] = 'Apache';
$string['th:affected'] = 'Affected';
$string['th:conclusion'] = 'Conclusion';
$string['reason:integration'] = 'A required external exam integration reported that initialisation failed.';
$string['reason:csp'] = 'The browser security policy blocked a required resource or connection.';
$string['reason:request'] = 'A required browser request failed.';
$string['reason:resource'] = 'A required script, stylesheet, image, or media resource failed to load.';
$string['reason:offline'] = 'The browser reported an offline transition.';
$string['reason:longtask'] = 'A browser main-thread task exceeded the delay threshold.';
$string['reason:jserror'] = 'The browser reported a JavaScript exception.';
$string['reason:render'] = 'Rendering after the response exceeded the delay threshold.';
$string['reason:apache'] = 'Apache measured slow PHP/Moodle request processing.';
$string['reason:ttfb'] = 'Time to first byte exceeded the delay threshold.';
$string['reason:transition'] = 'Page transition exceeded the threshold without a matching client signal.';
$string['reason:none'] = 'No configured incident threshold was exceeded.';
$string['reason:failedsubmission'] = 'Final submission was confirmed in the browser, but Moodle still reports the attempt as in progress.';
$string['privacy:metadata:apache'] = 'Apache request durations staged for correlation with a quiz attempt; the attempt links to the user.';
$string['privacy:metadata:apache:attemptid'] = 'The quiz attempt ID.';
$string['privacy:metadata:apache:durationms'] = 'How long the web server took to process the final submission request.';
$string['privacy:metadata:apache:timemodified'] = 'When the duration was read from the log.';
$string['privacy:metadata:apachelog'] = 'The optional Apache timing log configured by the site administrator is read, never written; its retention is controlled by the web server.';
$string['privacy:metadata:apachelog:referer'] = 'The request referrer, used only to find the quiz attempt ID.';
