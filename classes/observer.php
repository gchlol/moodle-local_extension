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
 * @package    mod_forum
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use local_extension\rule;
use local_extension\cm;
use local_extension\utility;


defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_extension
 */
class local_extension_observer {


    const CAPABILITY_ACCESS_ALL_COURSE_REQUESTS = 'local/extension:accessallcourserequests';

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {

        global $DB;
        $roleid         = $event->objectid;
        $contextid      = $event->contextid;
        $contextlevel   = $event->contextlevel;
        $userid         = $event->relateduserid;
        $courseid       = (int)$event->courseid;

        // The rules/triggers for this roleid.
        $triggers = $DB->get_records('local_extension_triggers', array('role' => $roleid));

        // Grab all the requestid and cmid for this course.
        $requests = $DB->get_records('local_extension_cm', array('course' => $courseid));

        // Get subs for this user.
        $params = [
            'userid' => $userid,
        ];

        $subs = $DB->get_records('local_extension_subscription', $params, 'id ASC');
        $currenttime = 1501760000;

        // Process the rule for each request.
        foreach ($requests as $request) {

            $requestobject = utility::cache_get_request($request->id);
            $mods = $requestobject->mods;

            foreach ($mods as $mod) {

                foreach ($triggers as $trigger) {

                    $rule = new rule($trigger->id);
                    $rule->context = $trigger->context;
                    $rule->name = $trigger->name;
                    $rule->action = $trigger->action;
                    $rule->role = $trigger->role;
                    $rule->priority = $trigger->priority;
                    $rule->parent = $trigger->parent;
                    $rule->lengthfromduedate = $trigger->lengthfromduedate;
                    $rule->lengthtype = $trigger->lengthtype;
                    $rule->elapsedfromrequest = $trigger->elapsedfromrequest;
                    $rule->elapsedtype = $trigger->elapsedfromrequest;
                    $rule->data = $trigger->data;
                    $rule->datatype = $trigger->datatype;

                    // Subscription should only be written when it passes rules.
                    if ($rule->rule_should_be_applied($requestobject, $mod, $currenttime)) {

                        if (empty($subs)) {
                            // Create a new record if it does not exist.
                            $sub = new stdClass();
                        } else {
                            // We now call get_records which returns an array, so pop the last value.
                            $sub = array_pop($records);
                        }

                        $sub->userid = $userid;
                        $sub->localcmid = $request->id;
                        $sub->requestid = $request->request;
                        $sub->lastmod = time();
                        $sub->trig = $trigger->id;
                        $sub->access = $trigger->action;

                        if (empty($sub->id)) {
                            $DB->insert_record('local_extension_subscription', $sub);
                        } else {
                            $DB->update_record('local_extension_subscription', $sub);
                        }
                    }
                }
            }
        }
        return;
    }
}

