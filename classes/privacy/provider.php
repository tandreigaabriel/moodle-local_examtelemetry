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

namespace local_examtelemetry\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Moodle Privacy API provider.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data stored.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_examtelemetry_event', [
            'userid' => 'privacy:metadata:event:userid',
            'courseid' => 'privacy:metadata:event:courseid',
            'cmid' => 'privacy:metadata:event:cmid',
            'quizid' => 'privacy:metadata:event:quizid',
            'attemptid' => 'privacy:metadata:event:attemptid',
            'eventtype' => 'privacy:metadata:event:eventtype',
            'eventdata' => 'privacy:metadata:event:eventdata',
            'useragent' => 'privacy:metadata:event:useragent',
            'timecreated' => 'privacy:metadata:event:timecreated',
        ], 'privacy:metadata:event');
        $collection->add_database_table('local_examtelemetry_inc', [
            'userid' => 'privacy:metadata:event:userid',
            'classification' => 'privacy:metadata:incident:classification',
            'responsibility' => 'privacy:metadata:incident:responsibility',
            'summary' => 'privacy:metadata:incident:summary',
        ], 'privacy:metadata:incident');
        $collection->add_database_table('local_examtelemetry_apache', [
            'attemptid' => 'privacy:metadata:apache:attemptid',
            'durationms' => 'privacy:metadata:apache:durationms',
            'timemodified' => 'privacy:metadata:apache:timemodified',
        ], 'privacy:metadata:apache');
        $collection->add_external_location_link('apachelog', [
            'referer' => 'privacy:metadata:apachelog:referer',
        ], 'privacy:metadata:apachelog');
        return $collection;
    }

    /**
     * Finds contexts containing user data.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {local_examtelemetry_event} e ON e.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel AND e.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Exports data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $events = $DB->get_records('local_examtelemetry_event', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
            if ($events) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_examtelemetry'), 'events'],
                    (object)array_values(array_map(static function ($event) {
                        $event->timecreated = transform::datetime($event->timecreated);
                        return $event;
                    }, $events))
                );
            }
            $incidents = $DB->get_records('local_examtelemetry_inc', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
            if ($incidents) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_examtelemetry'), 'incidents'],
                    (object)array_values(array_map(static function ($incident) {
                        $incident->timecreated = transform::datetime($incident->timecreated);
                        $incident->timemodified = transform::datetime($incident->timemodified);
                        return $incident;
                    }, $incidents))
                );
            }
        }
    }

    /**
     * Deletes data in a course context.
     *
     * @param \context $context Context to delete.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        self::delete_apache_for_course($context->instanceid);
        $DB->delete_records('local_examtelemetry_event', ['courseid' => $context->instanceid]);
        $DB->delete_records('local_examtelemetry_inc', ['courseid' => $context->instanceid]);
    }

    /**
     * Deletes user data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_apache_for_user_course($userid, $context->instanceid);
            $DB->delete_records('local_examtelemetry_event', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
            $DB->delete_records('local_examtelemetry_inc', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
        }
    }

    /**
     * Finds the users with telemetry in a course context.
     *
     * @param userlist $userlist Userlist to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_examtelemetry_event} WHERE courseid = :courseid', $params);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_examtelemetry_inc} WHERE courseid = :courseid', $params);
    }

    /**
     * Deletes the data of the given users in a course context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_apache_for_user_course((int) $userid, $context->instanceid);
            $DB->delete_records('local_examtelemetry_event', ['userid' => $userid, 'courseid' => $context->instanceid]);
            $DB->delete_records('local_examtelemetry_inc', ['userid' => $userid, 'courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes Apache data for course attempts.
     *
     * @param int $courseid Course ID.
     */
    private static function delete_apache_for_course(int $courseid): void {
        global $DB;
        $attemptids = $DB->get_fieldset_select(
            'local_examtelemetry_event',
            'DISTINCT attemptid',
            'courseid = :courseid',
            ['courseid' => $courseid]
        );
        if ($attemptids) {
            [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'apa');
            $DB->delete_records_select('local_examtelemetry_apache', "attemptid $insql", $params);
        }
    }

    /**
     * Deletes Apache data for user attempts.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     */
    private static function delete_apache_for_user_course(int $userid, int $courseid): void {
        global $DB;
        $attemptids = $DB->get_fieldset_select(
            'local_examtelemetry_event',
            'DISTINCT attemptid',
            'userid = :userid AND courseid = :courseid',
            ['userid' => $userid, 'courseid' => $courseid]
        );
        if ($attemptids) {
            [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'apu');
            $DB->delete_records_select('local_examtelemetry_apache', "attemptid $insql", $params);
        }
    }
}
