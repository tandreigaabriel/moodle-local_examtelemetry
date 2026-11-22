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

namespace local_examtelemetry\task;

/**
 * Incrementally aggregate changed attempts and refresh their incidents.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classify_incidents extends \core\task\scheduled_task {
    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskclassify', 'local_examtelemetry');
    }

    /**
     * Runs the scheduled task.
     */
    public function execute(): void {
        global $DB;

        \local_examtelemetry\apache_reader::ingest();
        $limit = max(20, min(1000, (int)(get_config('local_examtelemetry', 'classifierbatchsize') ?: 200)));
        $attemptids = [];

        // Prioritise confirmed submissions that still have not changed Moodle state after the grace period.
        $cutoff = time() - 120;
        $sql = "SELECT DISTINCT i.attemptid, i.timemodified
                  FROM {local_examtelemetry_inc} i
                  JOIN {quiz_attempts} qa ON qa.id = i.attemptid
                  JOIN {local_examtelemetry_event} e ON e.attemptid = i.attemptid
                 WHERE (qa.state = :inprogress
                        AND e.eventtype = :confirmed
                        AND e.timecreated <= :cutoff
                        AND i.classification <> :failed)
                    OR (qa.state <> :inprogress2
                        AND i.classification = :failed2)
              ORDER BY i.timemodified ASC";
        $params = [
            'inprogress' => 'inprogress',
            'confirmed' => 'final_submission_confirmed',
            'cutoff' => $cutoff,
            'failed' => 'failed_final_submission',
            'inprogress2' => 'inprogress',
            'failed2' => 'failed_final_submission',
        ];
        foreach ($DB->get_records_sql($sql, $params, 0, $limit) as $candidate) {
            $attemptids[(int)$candidate->attemptid] = true;
        }

        // An event ID is immutable and more reliable than comparing second-resolution timestamps.
        $sql = "SELECT e.attemptid, MAX(e.id) AS maxeventid
                  FROM {local_examtelemetry_event} e
                 WHERE NOT EXISTS (
                           SELECT 1
                             FROM {local_examtelemetry_inc} i
                            WHERE i.attemptid = e.attemptid
                              AND i.lasteventid >= e.id
                       )
              GROUP BY e.attemptid
              ORDER BY MAX(e.id) ASC";
        $remaining = $limit - count($attemptids);
        if ($remaining > 0) {
            foreach ($DB->get_records_sql($sql, [], 0, $remaining) as $candidate) {
                $attemptids[(int)$candidate->attemptid] = true;
            }
        }

        // Apache data can arrive after the browser events were already classified.
        $remaining = $limit - count($attemptids);
        if ($remaining > 0) {
            $sql = "SELECT DISTINCT a.attemptid, a.timemodified
                      FROM {local_examtelemetry_apache} a
                      JOIN {local_examtelemetry_event} e ON e.attemptid = a.attemptid
                 LEFT JOIN {local_examtelemetry_inc} i ON i.attemptid = a.attemptid
                     WHERE i.id IS NULL OR a.timemodified > i.timemodified
                  ORDER BY a.timemodified ASC";
            foreach ($DB->get_records_sql($sql, [], 0, $remaining) as $candidate) {
                $attemptids[(int)$candidate->attemptid] = true;
            }
        }

        foreach (array_keys($attemptids) as $attemptid) {
            $this->classify_attempt($attemptid);
        }
        $this->refresh_recent_clusters();
    }

    /**
     * Classify one changed attempt and preserve its administrative state.
     *
     * @param int $attemptid Attempt ID.
     */
    private function classify_attempt(int $attemptid): void {
        global $DB;

        $events = $DB->get_records(
            'local_examtelemetry_event',
            ['attemptid' => $attemptid],
            'timecreated ASC, id ASC'
        );
        if (!$events) {
            return;
        }
        $existing = $DB->get_record('local_examtelemetry_inc', ['attemptid' => $attemptid]);
        $apache = $DB->get_record('local_examtelemetry_apache', ['attemptid' => $attemptid]);
        $apachems = $apache ? (float)$apache->durationms : ($existing->apachems ?? null);
        $result = \local_examtelemetry\classifier::classify($events, $apachems);
        $attemptstate = $DB->get_field('quiz_attempts', 'state', ['id' => $attemptid]);
        $eventtypes = array_column($events, 'eventtype');
        $last = end($events);
        $confirmedat = 0;
        foreach ($events as $event) {
            if ($event->eventtype === 'final_submission_confirmed') {
                $confirmedat = max($confirmedat, (int)$event->timecreated);
            }
        }
        if (
            in_array('final_submission_confirmed', $eventtypes, true) &&
                $attemptstate === 'inprogress' && time() - $confirmedat > 120
        ) {
            $result['classification'] = 'failed_final_submission';
            $result['severity'] = 'critical';
            $result['confidence'] = 0.98;
            $result['responsibility'] = ($result['apachems'] ?? 0) > 5000
                ? 'moodle_server' : 'unknown_shared';
            $result['summary'] = get_string('reason:failedsubmission', 'local_examtelemetry');
        }
        $first = reset($events);
        $now = time();
        $record = (object)([
            'userid' => $first->userid,
            'courseid' => $first->courseid,
            'cmid' => $first->cmid,
            'quizid' => $first->quizid,
            'attemptid' => $attemptid,
            'status' => $result['classification'] === 'normal' ? 'not_applicable' : 'open',
            'notes' => '',
            'clusterkey' => null,
            'clustercount' => 1,
            'lasteventid' => max(array_keys($events)),
            'timefirstevent' => $first->timecreated,
            'timelastevent' => $last->timecreated,
            'timecreated' => $now,
            'timemodified' => $now,
        ] + $result);

        if ($existing) {
            $record->id = $existing->id;
            if ($result['classification'] === 'normal') {
                $record->status = 'not_applicable';
            } else if ($existing->classification === 'normal' || $existing->status === 'not_applicable') {
                $record->status = 'open';
            } else {
                $record->status = $existing->status;
            }
            $record->notes = $existing->notes;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_examtelemetry_inc', $record);
        } else {
            $DB->insert_record('local_examtelemetry_inc', $record);
        }
    }

    /**
     * Escalate incidents shared by multiple students in the latest ten-minute window.
     */
    private function refresh_recent_clusters(): void {
        global $DB;

        $clustersince = time() - (10 * MINSECS);
        $sql = "SELECT cmid, COUNT(DISTINCT userid) AS affected
                  FROM {local_examtelemetry_inc}
                 WHERE timelastevent >= :since AND classification <> :normal
              GROUP BY cmid
                HAVING COUNT(DISTINCT userid) >= 2";
        foreach ($DB->get_records_sql($sql, ['since' => $clustersince, 'normal' => 'normal']) as $cluster) {
            $affected = (int)$cluster->affected;
            $clusterkey = 'quiz:' . $cluster->cmid . ':' . floor(time() / (10 * MINSECS));
            $incidents = $DB->get_records_select(
                'local_examtelemetry_inc',
                'cmid = :cmid AND timelastevent >= :since AND classification <> :normal',
                ['cmid' => $cluster->cmid, 'since' => $clustersince, 'normal' => 'normal']
            );
            foreach ($incidents as $incident) {
                if ((int)$incident->clustercount <= 1) {
                    $severity = match ($incident->severity) {
                        'info' => 'medium',
                        'medium' => 'high',
                        default => 'critical',
                    };
                    $DB->set_field('local_examtelemetry_inc', 'severity', $severity, ['id' => $incident->id]);
                }
                $DB->set_field('local_examtelemetry_inc', 'clusterkey', $clusterkey, ['id' => $incident->id]);
                $DB->set_field('local_examtelemetry_inc', 'clustercount', $affected, ['id' => $incident->id]);
            }
        }
    }
}
