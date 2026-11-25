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
 * Tests for collector helpers.
 *
 * @package local_examtelemetry
 * @copyright 2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_examtelemetry;

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/fixtures/fake_license.php');

/**
 * Collector helper tests.
 *
 * @covers ::local_examtelemetry_clean_details
 * @covers ::local_examtelemetry_collection_allowed
 */
final class lib_test extends \advanced_testcase {
    public function test_clean_details_limits(): void {
        $this->resetAfterTest();
        $this->assertCount(20, local_examtelemetry_clean_details(array_fill_keys(range(1, 25), 'x')));
        $this->assertSame(str_repeat('x', 500), local_examtelemetry_clean_details(['a' => str_repeat('x', 10000)])['a']);
        $cleaned = local_examtelemetry_clean_details(array_fill_keys(range(1, 20), str_repeat('x', 500)));
        $this->assertLessThanOrEqual(4096, strlen(json_encode($cleaned, JSON_UNESCAPED_SLASHES)));
        $this->assertLessThan(20, count($cleaned));
        $this->assertSame(['ok' => null], local_examtelemetry_clean_details((object)['bad' => [], 'other' => (object)[], 'ok' => null]));
    }

    public function test_collection_allowed_requires_setting_and_license(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_examtelemetry');
        $this->assertFalse(local_examtelemetry_collection_allowed());
        fake_license::$reply = [200, json_encode(['status' => 'ok', 'plan' => 'perpetual',
            'expires_at' => time() + 100, 'support_until' => time() + 100]), 0];
        $this->assertTrue(fake_license::activate('TEST-KEY'));
        $this->assertTrue(local_examtelemetry_collection_allowed());
        set_config('enabled', 0, 'local_examtelemetry');
        $this->assertFalse(local_examtelemetry_collection_allowed());
    }
}
