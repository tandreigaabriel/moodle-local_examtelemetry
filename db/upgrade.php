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
 * Upgrade functionality.
 *
 * @package    local_examtelemetry
 * @copyright  2026 TAG Digital Solutions Ltd <https://tandreig.com>
 * @author     Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin schema.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_examtelemetry_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026072801) {
        $event = new xmldb_table('local_examtelemetry_event');
        $eventhash = new xmldb_field('eventhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        if (!$dbman->field_exists($event, $eventhash)) {
            $dbman->add_field($event, $eventhash);
        }
        $DB->execute("UPDATE {local_examtelemetry_event} SET eventhash = " .
            $DB->sql_concat("'legacy-'", 'id') . " WHERE eventhash = ''");
        $eventhashindex = new xmldb_index('eventhashuniq', XMLDB_INDEX_UNIQUE, ['eventhash']);
        if (!$dbman->index_exists($event, $eventhashindex)) {
            $dbman->add_index($event, $eventhashindex);
        }

        $incident = new xmldb_table('local_examtelemetry_inc');
        $fields = [
            new xmldb_field('apachems', XMLDB_TYPE_NUMBER, '12, 3', null, null, null, null, 'serverwaitms'),
            new xmldb_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status'),
            new xmldb_field('clusterkey', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'notes'),
            new xmldb_field('clustercount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'clusterkey'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($incident, $field)) {
                $dbman->add_field($incident, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026072801, 'local', 'examtelemetry');
    }
    if ($oldversion < 2026072802) {
        $incident = new xmldb_table('local_examtelemetry_inc');
        $lasteventid = new xmldb_field(
            'lasteventid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'clustercount'
        );
        if (!$dbman->field_exists($incident, $lasteventid)) {
            $dbman->add_field($incident, $lasteventid);
        }
        // Existing incidents are already classified through their current last event.
        $recordset = $DB->get_recordset_sql(
            "SELECT i.id, COALESCE(MAX(e.id), 0) AS lasteventid
               FROM {local_examtelemetry_inc} i
          LEFT JOIN {local_examtelemetry_event} e ON e.attemptid = i.attemptid
           GROUP BY i.id"
        );
        foreach ($recordset as $record) {
            $DB->set_field(
                'local_examtelemetry_inc',
                'lasteventid',
                (int)$record->lasteventid,
                ['id' => $record->id]
            );
        }
        $recordset->close();

        $apache = new xmldb_table('local_examtelemetry_apache');
        $apache->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $apache->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $apache->add_field('durationms', XMLDB_TYPE_NUMBER, '12, 3', null, XMLDB_NOTNULL);
        $apache->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $apache->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $apache->add_index('attemptuniq', XMLDB_INDEX_UNIQUE, ['attemptid']);
        $apache->add_index('timemodified', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);
        if (!$dbman->table_exists($apache)) {
            $dbman->create_table($apache);
        }
        upgrade_plugin_savepoint(true, 2026072802, 'local', 'examtelemetry');
    }
    if ($oldversion < 2026072803) {
        // Force Moodle to invalidate cached AMD bundles after correcting the production build format.
        $DB->set_field(
            'local_examtelemetry_inc',
            'status',
            'not_applicable',
            ['classification' => 'normal']
        );
        upgrade_plugin_savepoint(true, 2026072803, 'local', 'examtelemetry');
    }
    if ($oldversion < 2026072804) {
        $incident = new xmldb_table('local_examtelemetry_inc');
        $fields = [
            new xmldb_field(
                'responsibility',
                XMLDB_TYPE_CHAR,
                '30',
                null,
                XMLDB_NOTNULL,
                null,
                'unknown',
                'lasteventid'
            ),
            new xmldb_field(
                'resourceerrors',
                XMLDB_TYPE_INTEGER,
                '10',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'responsibility'
            ),
            new xmldb_field(
                'requestfailures',
                XMLDB_TYPE_INTEGER,
                '10',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'resourceerrors'
            ),
            new xmldb_field(
                'integrationfailures',
                XMLDB_TYPE_INTEGER,
                '10',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'requestfailures'
            ),
            new xmldb_field(
                'cspviolations',
                XMLDB_TYPE_INTEGER,
                '10',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'integrationfailures'
            ),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($incident, $field)) {
                $dbman->add_field($incident, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026072804, 'local', 'examtelemetry');
    }
    return true;
}
