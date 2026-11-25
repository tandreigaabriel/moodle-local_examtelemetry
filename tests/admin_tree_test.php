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
 * Tests for the report admin tree entry.
 *
 * @package local_examtelemetry
 * @copyright 2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_examtelemetry;

/**
 * Report access tests.
 *
 * @coversNothing
 */
final class admin_tree_test extends \advanced_testcase {
    public function test_report_access_without_site_configuration(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $role = create_role('Report viewer', 'examtelemetryviewer', '');
        $context = \context_system::instance();
        assign_capability('local/examtelemetry:viewreports', CAP_ALLOW, $role, $context);
        role_assign($role, $user->id, $context);
        $this->setUser($user);
        $this->assertFalse(has_capability('moodle/site:config', $context));
        $page = admin_get_root(true, false)->locate('local_examtelemetry_report');
        $this->assertNotNull($page);
        $this->assertTrue($page->check_access());
        $this->setUser($this->getDataGenerator()->create_user());
        $page = admin_get_root(true, false)->locate('local_examtelemetry_report');
        $this->assertNotNull($page);
        $this->assertFalse($page->check_access());
    }
}
