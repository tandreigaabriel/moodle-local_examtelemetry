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
 * Incremental Apache log reader tests.
 *
 * @package    local_examtelemetry
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_examtelemetry\apache_reader
 */
final class apache_reader_test extends \advanced_testcase {
    public function test_reader_resumes_and_waits_for_complete_lines(): void {
        global $DB;

        $this->resetAfterTest();
        $directory = make_request_directory();
        $path = $directory . '/quiz-timing.log';
        set_config('apachelogpath', $path, 'local_examtelemetry');
        file_put_contents(
            $path,
            "duration_us=1500000 referer=\"/mod/quiz/summary.php?attempt=42\"\n" .
            "duration_us=9000000 referer=\"/mod/quiz/summary.php?attempt=43\""
        );

        $this->assertSame(1, apache_reader::ingest());
        $this->assertEquals(
            1500.0,
            $DB->get_field('local_examtelemetry_apache', 'durationms', ['attemptid' => 42])
        );
        $this->assertFalse($DB->record_exists('local_examtelemetry_apache', ['attemptid' => 43]));

        file_put_contents($path, "\n", FILE_APPEND);
        $this->assertSame(1, apache_reader::ingest());
        $this->assertEquals(
            9000.0,
            $DB->get_field('local_examtelemetry_apache', 'durationms', ['attemptid' => 43])
        );
        $this->assertSame(0, apache_reader::ingest());
    }

    public function test_reader_keeps_longest_duration_for_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $directory = make_request_directory();
        $path = $directory . '/quiz-timing.log';
        set_config('apachelogpath', $path, 'local_examtelemetry');
        file_put_contents(
            $path,
            "duration_us=5000000 referer=\"?attempt=7\"\n" .
            "duration_us=2000000 referer=\"?attempt=7\"\n"
        );

        $this->assertSame(2, apache_reader::ingest());
        $this->assertEquals(
            5000.0,
            $DB->get_field('local_examtelemetry_apache', 'durationms', ['attemptid' => 7])
        );
    }
}
