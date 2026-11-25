<?php
// This file is part of Moodle - https://moodle.org/
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
 * Licence activation page.
 *
 * @package    local_examtelemetry
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_examtelemetry\local\license;

require_login();
require_capability('moodle/site:config', context_system::instance());
$url = new moodle_url('/local/examtelemetry/license.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('licenseheading', 'local_examtelemetry'));
$PAGE->set_heading(get_string('licenseheading', 'local_examtelemetry'));
if (data_submitted()) {
    require_sesskey();
    $key = required_param('key', PARAM_RAW_TRIMMED);
    $ok = license::activate($key);
    redirect(
        $url,
        get_string($ok ? 'licenseactivated' : 'licensefailed', 'local_examtelemetry'),
        null,
        $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
    );
}
$p = 'local_examtelemetry';
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('licenseheading', $p));
$status = license::get_status();
echo html_writer::tag('p', get_string('license_' . $status, $p));
$info = license::get_info();
if ($info && $status !== 'unlicensed' && in_array($info->plan, ['trial', 'perpetual'], true)) {
    echo html_writer::tag('p', get_string('licenseplan', $p, get_string('licenseplan_' . $info->plan, $p)));
    if ($info->plan === 'trial' && !empty($info->expires_at)) {
        echo html_writer::tag('p', get_string('licensetrialends', $p, userdate($info->expires_at)));
    }
    if (!empty($info->support_until)) {
        echo html_writer::tag('p', get_string('licensesupport', $p, userdate($info->support_until)));
    }
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('licensekey', $p), ['for' => 'licensekey', 'class' => 'form-label']);
echo html_writer::start_div('d-flex flex-wrap gap-2');
echo html_writer::empty_tag('input', [
    'type' => 'password',
    'name' => 'key',
    'id' => 'licensekey',
    'required' => 'required',
    'autocomplete' => 'off',
    'class' => 'form-control w-auto',
    'size' => 34,
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('licenseactivate', $p),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo license::purchase_info();
echo $OUTPUT->footer();
