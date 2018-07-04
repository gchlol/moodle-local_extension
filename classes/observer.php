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
 * Event observers used in local_extension.
 *
 */


use local_extension\rule;
use local_extension\cm;
use local_extension\utility;


defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_extension
 */
class local_extension_observer {

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {

        global $DB;

        $roleid         = $event->objectid;
        $userid         = $event->relateduserid;
        $courseid       = (int)$event->courseid;

        // The rules/triggers for this roleid.
        $triggers = $DB->get_records('local_extension_triggers', array('role' => $roleid));

        // Do not need to write new subscriptions if there are no rules for this role.
        if (!$triggers) {
            return;
        }

        // Grab all the requestid and cmid for this course.
        $requests = $DB->get_records('local_extension_cm', array('course' => $courseid));

        $currenttime = time();

        // Process the rule for each request and module.
        foreach ($requests as $request) {

            $requestobject = utility::cache_get_request($request->request);
            $mods = $requestobject->mods;

            foreach ($mods as $mod) {

                foreach ($triggers as $trigger) {

                    $rule = rule::load_rule_from_trigger($trigger);

                    $rule->process_for_one_user($requestobject, $mod, $currenttime, $userid);
                }
            }
        }
        return;
    }
}
