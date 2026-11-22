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
