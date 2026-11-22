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
 * Settings functionality.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_examtelemetry', get_string('pluginname', 'local_examtelemetry'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_examtelemetry/enabled',
        get_string('settingsenabled', 'local_examtelemetry'),
        get_string('settingsenabled_desc', 'local_examtelemetry'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/enabledcmids',
        get_string('enabledcmids', 'local_examtelemetry'),
        get_string('enabledcmids_desc', 'local_examtelemetry'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/longtaskthreshold',
        get_string('longtaskthreshold', 'local_examtelemetry'),
        get_string('longtaskthreshold_desc', 'local_examtelemetry'),
        500,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/delaythreshold',
        get_string('delaythreshold', 'local_examtelemetry'),
        get_string('delaythreshold_desc', 'local_examtelemetry'),
        5000,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/retentiondays',
        get_string('retentiondays', 'local_examtelemetry'),
        get_string('retentiondays_desc', 'local_examtelemetry'),
        30,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/ratelimit',
        get_string('ratelimit', 'local_examtelemetry'),
        get_string('ratelimit_desc', 'local_examtelemetry'),
        120,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/apachelogpath',
        get_string('apachelogpath', 'local_examtelemetry'),
        get_string('apachelogpath_desc', 'local_examtelemetry'),
        '',
        PARAM_RAW_TRIMMED
    ));
    $settings->add(new admin_setting_configtext(
        'local_examtelemetry/classifierbatchsize',
        get_string('classifierbatchsize', 'local_examtelemetry'),
        get_string('classifierbatchsize_desc', 'local_examtelemetry'),
        200,
        PARAM_INT
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_examtelemetry_report',
        get_string('report', 'local_examtelemetry'),
        new moodle_url('/local/examtelemetry/report.php'),
        'local/examtelemetry:viewreports'
    ));
}
