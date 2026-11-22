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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_examtelemetry\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Privacy API tests.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_examtelemetry\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Insert one event, one incident and one Apache row for a user in a course.
     *
     * @param int $userid User.
     * @param int $courseid Course.
     * @param int $attemptid Attempt id (also used as cmid/quizid).
     */
    private function seed(int $userid, int $courseid, int $attemptid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_examtelemetry_event', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'cmid' => $attemptid, 'quizid' => $attemptid,
            'attemptid' => $attemptid, 'eventtype' => 'long_task', 'pagekind' => 'attempt', 'durationms' => 1200,
            'eventdata' => '{}', 'useragent' => 'Test', 'eventhash' => sha1((string) $attemptid), 'timecreated' => $now,
        ]);
        $DB->insert_record('local_examtelemetry_inc', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'cmid' => $attemptid, 'quizid' => $attemptid,
            'attemptid' => $attemptid, 'classification' => 'browser_js_freeze', 'severity' => 'medium', 'confidence' => 0.9,
            'summary' => 'test', 'timefirstevent' => $now, 'timelastevent' => $now, 'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_examtelemetry_apache', (object) [
            'attemptid' => $attemptid, 'durationms' => 3400, 'timemodified' => $now,
        ]);
    }

    /**
     * All three tables and the Apache log are declared.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new collection('local_examtelemetry'));
        $tables = [];
        foreach ($collection->get_collection() as $item) {
            $tables[] = $item->get_name();
        }
        $this->assertContains('local_examtelemetry_event', $tables);
        $this->assertContains('local_examtelemetry_inc', $tables);
        $this->assertContains('local_examtelemetry_apache', $tables);
        $this->assertContains('apachelog', $tables);
    }

    /**
     * Contexts, users in context, export and the two deletion paths.
     */
    public function test_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $this->seed($user->id, $course->id, 11);
        $this->seed($second->id, $course->id, 12);
        $this->seed($user->id, $other->id, 13);
        $coursecontext = \context_course::instance($course->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertEqualsCanonicalizing(
            [$coursecontext->id, \context_course::instance($other->id)->id],
            $contextlist->get_contextids()
        );

        $userlist = new userlist($coursecontext, 'local_examtelemetry');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user->id, $second->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($user, 'local_examtelemetry', [$coursecontext->id]));
        $writer = writer::with_context($coursecontext);
        $this->assertTrue($writer->has_any_data());
        $events = $writer->get_data([get_string('pluginname', 'local_examtelemetry'), 'events']);
        $this->assertCount(1, (array) $events);

        // Delete one user in one course: the other user and the other course keep their rows, Apache rows follow.
        provider::delete_data_for_user(new approved_contextlist($user, 'local_examtelemetry', [$coursecontext->id]));
        $this->assertEquals(0, $DB->count_records('local_examtelemetry_event', ['userid' => $user->id, 'courseid' => $course->id]));
        $this->assertEquals(0, $DB->count_records('local_examtelemetry_apache', ['attemptid' => 11]));
        $this->assertEquals(1, $DB->count_records('local_examtelemetry_apache', ['attemptid' => 12]));
        $this->assertEquals(1, $DB->count_records('local_examtelemetry_event', ['userid' => $user->id, 'courseid' => $other->id]));

        // Delete a list of users in the context.
        $this->seed($user->id, $course->id, 14);
        provider::delete_data_for_users(new approved_userlist($coursecontext, 'local_examtelemetry', [$user->id, $second->id]));
        $this->assertEquals(0, $DB->count_records('local_examtelemetry_inc', ['courseid' => $course->id]));
        $this->assertEquals(0, $DB->count_records('local_examtelemetry_apache', ['attemptid' => 12]));

        // Delete everything in the other course.
        provider::delete_data_for_all_users_in_context(\context_course::instance($other->id));
        $this->assertEquals(0, $DB->count_records('local_examtelemetry_event'));
        $this->assertEquals(0, $DB->count_records('local_examtelemetry_apache'));
    }
}
