<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Upgrade script for local_extension.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_local_extension_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2017051600) {

        // Rename field trigger on table local_extension_history_trig to trig.
        $table = new xmldb_table('local_extension_history_trig');
        $field = new xmldb_field('trigger', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        // Launch rename field trig.
        $dbman->rename_field($table, $field, 'trig');

        // Rename field trigger on table local_extension_subscription to trig.
        $table = new xmldb_table('local_extension_subscription');
        $field = new xmldb_field('trigger', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        // Launch rename field trig.
        $dbman->rename_field($table, $field, 'trig');

        // Define table local_extension_history_trig to be renamed to local_extension_hist_trig.
        $table = new xmldb_table('local_extension_history_trig');
        // Launch rename table for local_extension_history_trig.
        $dbman->rename_table($table, 'local_extension_hist_trig');

        // Define table local_extension_history_file to be renamed to local_extension_hist_file.
        $table = new xmldb_table('local_extension_history_file');
        // Launch rename table for local_extension_history_file.
        $dbman->rename_table($table, 'local_extension_hist_file');

        // Define table local_extension_his_state to be renamed to local_extension_hist_state.
        $table = new xmldb_table('local_extension_his_state');
        // Launch rename table for local_extension_his_state.
        $dbman->rename_table($table, 'local_extension_hist_state');

        // Extension savepoint reached.
        upgrade_plugin_savepoint(true, 2017051600, 'local', 'extension');
    }

    return true;
}
