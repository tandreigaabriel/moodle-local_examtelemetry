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
 * Deterministic, explainable incident classifier.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classifier {
    /**
     * Classify all events for one attempt.
     *
     * @param array $events Event records.
     * @param float|null $apachems Apache request duration.
     * @return array Classification fields.
     */
    public static function classify(array $events, ?float $apachems = null): array {
        $threshold = max(1000, (int)get_config('local_examtelemetry', 'delaythreshold'));
        $metrics = [
            'transitionms' => null,
            'serverwaitms' => null,
            'apachems' => $apachems,
            'renderms' => null,
            'longesttaskms' => null,
            'jserrors' => 0,
            'offlineevents' => 0,
            'resourceerrors' => 0,
            'requestfailures' => 0,
            'integrationfailures' => 0,
            'cspviolations' => 0,
        ];
        $lastrequest = [];
        $lastresource = [];

        foreach ($events as $event) {
            $details = json_decode($event->eventdata ?? '{}', true) ?: [];
            if ($event->eventtype === 'long_task') {
                $metrics['longesttaskms'] = max((float)($metrics['longesttaskms'] ?? 0), (float)$event->durationms);
            } else if (in_array($event->eventtype, ['js_error', 'unhandled_rejection'], true)) {
                $metrics['jserrors']++;
            } else if ($event->eventtype === 'offline') {
                $metrics['offlineevents']++;
            } else if ($event->eventtype === 'resource_load_error') {
                $metrics['resourceerrors']++;
                $lastresource = $details;
            } else if ($event->eventtype === 'http_failure') {
                $metrics['requestfailures']++;
                $lastrequest = $details;
            } else if ($event->eventtype === 'integration_failure') {
                $metrics['integrationfailures']++;
            } else if ($event->eventtype === 'csp_violation') {
                $metrics['cspviolations']++;
            } else if ($event->eventtype === 'event_loop_lag') {
                $metrics['longesttaskms'] = max((float)($metrics['longesttaskms'] ?? 0), (float)$event->durationms);
            } else if ($event->eventtype === 'transition_timing') {
                $metrics['transitionms'] = (float)$event->durationms;
            } else if ($event->eventtype === 'navigation_timing') {
                $metrics['serverwaitms'] = isset($details['serverwaitms']) ? (float)$details['serverwaitms'] : null;
                $metrics['renderms'] = isset($details['renderms']) ? (float)$details['renderms'] : null;
            }
        }

        $classification = 'normal';
        $severity = 'info';
        $confidence = 0.95;
        $responsibility = 'none';
        $reasons = [];

        $duration = max(
            (float)($metrics['transitionms'] ?? 0),
            (float)($metrics['serverwaitms'] ?? 0),
            (float)($metrics['renderms'] ?? 0),
            (float)($metrics['longesttaskms'] ?? 0),
            (float)($metrics['apachems'] ?? 0)
        );
        $severityforduration = static function (float $milliseconds): string {
            if ($milliseconds > 30000) {
                return 'critical';
            }
            if ($milliseconds > 15000) {
                return 'high';
            }
            return $milliseconds > 5000 ? 'medium' : 'info';
        };
        if ($metrics['integrationfailures'] > 0) {
            $classification = 'external_integration_failure';
            $severity = 'critical';
            $confidence = 0.98;
            $responsibility = 'external_service';
            $reasons[] = get_string('reason:integration', 'local_examtelemetry');
        } else if ($metrics['cspviolations'] > 0) {
            $classification = 'security_policy_failure';
            $severity = 'high';
            $confidence = 0.92;
            $responsibility = 'exam_configuration';
            $reasons[] = get_string('reason:csp', 'local_examtelemetry');
        } else if ($metrics['requestfailures'] > 0) {
            $category = $lastrequest['category'] ?? 'unknown';
            $status = (int)($lastrequest['status'] ?? 0);
            if ($status === 0) {
                $classification = 'network_request_failure';
                $responsibility = 'student_network';
            } else if ($category === 'moodle') {
                $classification = 'moodle_request_failure';
                $responsibility = 'moodle_server';
            } else {
                $classification = 'external_service_failure';
                $responsibility = 'external_service';
            }
            $severity = $status >= 500 || $status === 0 ? 'high' : 'medium';
            $confidence = 0.88;
            $reasons[] = get_string('reason:request', 'local_examtelemetry');
        } else if ($metrics['resourceerrors'] > 0) {
            $isexternal = ($lastresource['category'] ?? '') === 'external';
            $classification = $isexternal ? 'external_resource_failure' : 'resource_load_failure';
            $severity = 'high';
            $confidence = 0.85;
            $responsibility = $isexternal ? 'external_service' : 'exam_configuration';
            $reasons[] = get_string('reason:resource', 'local_examtelemetry');
        } else if ($metrics['offlineevents'] > 0) {
            $classification = 'network_interruption';
            $severity = in_array($severityforduration($duration), ['critical'], true) ? 'critical' : 'high';
            $confidence = 0.98;
            $responsibility = 'student_network';
            $reasons[] = get_string('reason:offline', 'local_examtelemetry');
        } else if (($metrics['longesttaskms'] ?? 0) >= $threshold) {
            $classification = 'browser_js_freeze';
            $severity = $severityforduration((float)$metrics['longesttaskms']);
            $confidence = 0.97;
            $responsibility = 'student_device_browser';
            $reasons[] = get_string('reason:longtask', 'local_examtelemetry');
        } else if ($metrics['jserrors'] > 0) {
            $classification = 'javascript_error';
            $severity = $severityforduration($duration) === 'info' ? 'medium' : $severityforduration($duration);
            $confidence = 0.90;
            $responsibility = 'student_device_browser';
            $reasons[] = get_string('reason:jserror', 'local_examtelemetry');
        } else if (($metrics['renderms'] ?? 0) >= $threshold) {
            $classification = 'browser_render_delay';
            $severity = $severityforduration((float)$metrics['renderms']);
            $confidence = 0.88;
            $responsibility = 'student_device_browser';
            $reasons[] = get_string('reason:render', 'local_examtelemetry');
        } else if (($metrics['apachems'] ?? 0) >= $threshold) {
            $classification = 'server_delay';
            $severity = $severityforduration((float)$metrics['apachems']);
            $confidence = 0.96;
            $responsibility = 'moodle_server';
            $reasons[] = get_string('reason:apache', 'local_examtelemetry');
        } else if (($metrics['serverwaitms'] ?? 0) >= $threshold) {
            $classification = 'server_or_network_delay';
            $severity = $severityforduration((float)$metrics['serverwaitms']);
            $confidence = 0.75;
            $responsibility = 'unknown_shared';
            $reasons[] = get_string('reason:ttfb', 'local_examtelemetry');
        } else if (($metrics['transitionms'] ?? 0) >= $threshold) {
            $classification = 'unexplained_transition_delay';
            $severity = $severityforduration((float)$metrics['transitionms']);
            $confidence = 0.60;
            $responsibility = 'unknown';
            $reasons[] = get_string('reason:transition', 'local_examtelemetry');
        } else {
            $reasons[] = get_string('reason:none', 'local_examtelemetry');
        }

        return $metrics + [
            'classification' => $classification,
            'severity' => $severity,
            'confidence' => $confidence,
            'responsibility' => $responsibility,
            'summary' => implode(' ', $reasons),
        ];
    }
}
