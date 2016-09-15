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
 * Table utility class
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table utility class
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table {

    /**
     * The list of requests
     *
     * @return \flexible_table
     */
    public static function index_search_table() {
        global $PAGE;

        $headers = array(
            get_string('table_header_request', 'local_extension'),
            get_string('table_header_items', 'local_extension'),
            get_string('table_header_requestdate', 'local_extension'),
            get_string('table_header_lastmod', 'local_extension'),
            get_string('table_header_statushead', 'local_extension'),
            get_string('table_header_username', 'local_extension'),
        );

        $columns = array('id', 'request', 'timestamp', 'lastmod', 'status', 'userid');

        $table = new \flexible_table('local_extension_summary');
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->sortable(true);
        $table->pageable(true);

        $table->no_sorting('status');

        $table->define_baseurl($PAGE->url);
        $table->set_attribute('id', 'local_extension_table');
        $table->set_attribute('class', 'generaltable admintable');
        $table->setup();

        return $table;
    }

    /**
     * Generates the basic requirements the status page table.
     *
     * @return \flexible_table
     */
    public static function generate_trigger_table() {
        global $PAGE;

        $headers = array(
            get_string('table_header_rule_name', 'local_extension'),
            get_string('table_header_rule_action', 'local_extension'),
            get_string('table_header_rule_actionable', 'local_extension'),
            get_string('table_header_rule_parent', 'local_extension'),
            get_string('table_header_rule_datatype', 'local_extension'),
            get_string('table_header_rule_priority', 'local_extension'),
            get_string('table_header_rule_data', 'local_extension'),
            '',
        );

        $columns = array('name', 'action', 'role', 'parent', 'datatype', 'priority', 'data', '');

        $table = new \flexible_table('local_extension_triggers');
        $table->define_columns($columns);
        $table->define_headers($headers);

        $table->define_baseurl($PAGE->url);
        $table->set_attribute('id', 'local_extension_table');
        $table->set_attribute('class', 'generaltable admintable');
        $table->setup();

        return $table;
    }

}
