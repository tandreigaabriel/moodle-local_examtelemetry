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
 * Classifier rule tests.
 *
 * @package    local_examtelemetry
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_examtelemetry\classifier
 */
final class classifier_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('delaythreshold', 5000, 'local_examtelemetry');
    }

    /**
     * Build a synthetic telemetry event record.
     *
     * @param string $type Event type.
     * @param float|null $duration Duration in milliseconds.
     * @param array $details Event details.
     * @return object
     */
    private function event(string $type, ?float $duration = null, array $details = []): object {
        return (object)[
            'eventtype' => $type,
            'pagekind' => 'attempt',
            'durationms' => $duration,
            'eventdata' => json_encode($details),
            'timecreated' => time(),
        ];
    }

    public function test_normal_attempt(): void {
        $result = classifier::classify([
            $this->event('navigation_timing', 900, ['serverwaitms' => 400, 'renderms' => 100]),
            $this->event('transition_timing', 1400),
        ]);
        $this->assertSame('normal', $result['classification']);
        $this->assertSame('info', $result['severity']);
    }

    public function test_apache_timing_identifies_server_delay(): void {
        $result = classifier::classify([$this->event('telemetry_start')], 31000);
        $this->assertSame('server_delay', $result['classification']);
        $this->assertSame('critical', $result['severity']);
        $this->assertSame(31000.0, $result['apachems']);
    }

    public function test_offline_signal_has_priority(): void {
        $result = classifier::classify([
            $this->event('offline'),
            $this->event('long_task', 20000),
        ]);
        $this->assertSame('network_interruption', $result['classification']);
        $this->assertSame('high', $result['severity']);
    }

    public function test_duration_severity_boundaries(): void {
        $medium = classifier::classify([$this->event('transition_timing', 6000)]);
        $high = classifier::classify([$this->event('transition_timing', 16000)]);
        $critical = classifier::classify([$this->event('transition_timing', 31000)]);
        $this->assertSame('medium', $medium['severity']);
        $this->assertSame('high', $high['severity']);
        $this->assertSame('critical', $critical['severity']);
    }

    public function test_external_integration_failure_is_critical(): void {
        $result = classifier::classify([
            $this->event('integration_failure', null, [
                'integration' => 'exam_service',
                'event' => 'initialisation_failed',
                'reason' => 'domain_not_authorised',
            ]),
        ]);
        $this->assertSame('external_integration_failure', $result['classification']);
        $this->assertSame('critical', $result['severity']);
        $this->assertSame('external_service', $result['responsibility']);
        $this->assertSame(1, $result['integrationfailures']);
    }

    public function test_moodle_http_failure_assigns_server_responsibility(): void {
        $result = classifier::classify([
            $this->event('http_failure', null, ['category' => 'moodle', 'status' => 503]),
        ]);
        $this->assertSame('moodle_request_failure', $result['classification']);
        $this->assertSame('high', $result['severity']);
        $this->assertSame('moodle_server', $result['responsibility']);
    }

    public function test_event_loop_lag_supports_safari_freeze_detection(): void {
        $result = classifier::classify([$this->event('event_loop_lag', 7000)]);
        $this->assertSame('browser_js_freeze', $result['classification']);
        $this->assertSame('student_device_browser', $result['responsibility']);
        $this->assertSame(7000.0, $result['longesttaskms']);
    }
}
