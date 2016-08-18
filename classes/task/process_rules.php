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
 * Task: Process rules.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\task;

/**
 * Process rules task.
 *
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_rules extends \core\task\scheduled_task {
    /**
     * {@inheritDoc}
     * @see \core\task\scheduled_task::get_name()
     */
    public function get_name() {
        return get_string('task_process', 'local_extension');
    }

    /**
     * {@inheritDoc}
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        // TODO add request status, closed? to filter this query. When is a request closed?

        $sql = "SELECT id
                  FROM {local_extension_request}";

        $requestids = $DB->get_fieldset_sql($sql);

        foreach ($requestids as $requestid) {
            $request = \local_extension\request::from_id($requestid);
            $request->process_triggers();
        }
    }

}