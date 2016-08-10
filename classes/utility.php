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
 * Utility class
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Utility class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utility {
    /**
     * Returns a list of candidate dates for activities
     *
     * @param user $userid Userid or user object
     * @param timestamp $start  Start of search period
     * @param timestamp $end End of search period
     * @param array $options Optional arguments.
     * @return An array of candidates. array($mods, $events)
     *
     */
    public static function get_activities($userid, $start, $end, $options = null) {
        global $DB;

        $cid = !empty($options['courseid']) ? $options['courseid'] : 0;
        $cmid = !empty($options['moduleid']) ? $options['moduleid'] : 0;
        $requestid = !empty($options['requestid']) ? $options['requestid'] : 0;

        $dates = array();

        $mods = \local_extension\plugininfo\extension::get_enabled_request();

        // To be efficient we do a single search through the calendar and then
        // filter these events down to one's that can handle extensions.

        $groups = null;
        $courses = null;

        // Get the events matching our criteria.
        list($courses, $group, $user2) = calendar_set_filters(array());

        $allevents = calendar_get_events($start, $end, array($userid), $groups, true);

        $events = array();
        $courses = array();

        foreach ($allevents as $id => $event) {

            $modtype = $event->modulename;

            // First filter to only activities that have an extension plugin.
            if (!isset($mods[$modtype])) {
                continue;
            }

            $handler = $mods[$modtype];

            if (!$cm = get_coursemodule_from_instance($event->modulename, $event->instance)) {
                continue;
            }

            if (!\core_availability\info_module::is_user_visible($cm, 0, false)) {
                continue;
            }

            // Now give the handler a chance to filter, for instance an activity
            // could have a open, due and close, but it may only really care about
            // the due date.
            if (!$handler->is_candidate($event, $cm)) {
                continue;
            }

            // Filter based on moduleid.
            if (!empty($cmid)) {
                if ($cm->id != $cmid) {
                    continue;
                }
            }

            // Filter based on courseid.
            if (!empty($cid)) {
                if ($cm->course != $cid) {
                    continue;
                }
            }

            $courseid = $cm->course;
            if (!isset($courses[$courseid])) {
                $courses[$courseid] = $DB->get_record('course', array('id' => $courseid));
            }

            // If a requestid has been provided, obtain the local cm data for this mod.
            $localcm = null;
            if (!empty($requestid)) {
                $localcm = \local_extension\cm::from_requestid($cm->id, $requestid);

                // A requestid has been specified, lets list the available local_cms.

                // No local_extension_cm found, we won't need to provide an event.
                if (empty($localcm->cm)) {
                    continue;
                }

            } else {
                // Try and obtain a request associated to this user for a course module.
                $localcm = \local_extension\cm::from_userid($cm->id, $userid);
            }

            $events[$cm->id] = array(
                    'event' => $event,
                    'cm' => $cm,
                    'localcm' => $localcm,
                    'course' => $courses[$courseid],
                    'handler' => $handler,
            );
        }

        return array($mods, $events);
    }

    /**
     * Sends a status email to the student.
     * TODO Implement sending to course coordinators with the capabilitiy and role to grant extensions.
     *
     * @param integer $requestid
     */
    public static function send_status_email($requestid) {
    }

    /**
     * Sends a notification email to the roles speficied in a rule.
     *
     * @param string $subject
     * @param string $content
     * @param stdClass $user
     */
    public static function send_trigger_email($subject, $content, $user) {
        $noreplyuser = \core_user::get_support_user();

        $message = new \stdClass();
        $message->component         = 'local_extension';
        $message->name              = 'status';
        $message->userfrom          = $noreplyuser;
        $message->userto            = $user;
        $message->subject           = $subject;
        $message->fullmessage       = $content;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = '';
        $message->smallmessage      = '';
        $message->notification      = 1;
        message_send($message);
    }

    /**
     * TODO
     * @param integer $courseid
     * @param integer $userid
     */
    public static function course_request_status($courseid, $userid) {
        global $DB;

    }

    /**
     * Returns the number of requests the user has for a specific course.
     * @param interger $courseid
     * @param interger $userid
     * @return integer count
     */
    public static function count_requests($courseid, $userid) {
        global $DB;

        $sql = "SELECT count(cm.request)
                  FROM {local_extension_cm} cm
                 WHERE userid = :userid
                   AND course = :courseid";

        $params = array('userid' => $userid, 'courseid' => $courseid);

        $record = $DB->get_record_sql($sql, $params);

        if (!empty($record)) {
            return $record->count;
        } else {
            return 0;
        }
    }

    /**
     * Obtains a request from the cache.
     *
     * @param integer $requestid
     * @return request A request object.
     */
    public static function cache_get_request($requestid) {
        $cache = \cache::make('local_extension', 'requests');
        return $cache->get($requestid);
    }

    /**
     * Returns an array of all requests from the cache for the user specified.
     *
     * @param integer $userid
     * @return request[] An array of requests.
     */
    public static function cache_get_requests($userid = 0) {
        global $DB;

        if (!empty($userid)) {
            $where = " WHERE r.userid = ? ";
            $params = array('userid' => $userid);
        } else {
            $where = '';
            $params = array();
        }

        $sql = "SELECT r.id
                  FROM {local_extension_request} r
                $where";

        $requestids = $DB->get_fieldset_sql($sql, $params);

        $cache = \cache::make('local_extension', 'requests');
        return $cache->get_many($requestids);
    }

    /**
     * When a request has been modified this will invalidate the cache for that requestid.
     *
     * @param integer $requestid
     */
    public static function cache_invalidate_request($requestid) {
        $cache = \cache::make('local_extension', 'requests');
        $cache->delete($requestid);
    }

    /**
     * Returns the requests for a given courseid.
     *
     * @param integer $courseid
     * @return \local_extension\request[]
     */
    public static function find_course_requests($courseid) {
        global $USER;

        $requests = self::cache_get_requests($USER->id);

        $matchedrequests = array();

        // Return matching requests for a course.
        foreach ($requests as $request) {
            foreach ($request->cms as $cm) {
                if ($courseid == $cm->get_courseid()) {
                    $matchedrequests[$cm->requestid] = $request;
                    break;
                }
            }
        }

        return $matchedrequests;
    }

    /**
     * Returns the cm and request.
     *
     * @param integer $courseid
     * @param integer $moduleid
     * @return \local_extension\request[]|unknown[]
     */
    public static function find_module_requests($courseid, $moduleid) {
        global $USER;

        $requests = self::cache_get_requests($USER->id);

        foreach ($requests as $request) {
            foreach ($request->cms as $cm) {
                if ($courseid == $cm->get_courseid() && $moduleid == $cm->get_cmid()) {
                    return array($request, $cm);
                }
            }
        }
    }

    /**
     * Returns the input rules in sorted order.
     *
     * Sorted based on the priorty and grouped with parents.
     *
     * @param \local_extension\rule[] $rules
     */
    public static function sort_rules($rules) {

        // Sort all the rules based on priority.
        usort($rules, function($a, $b) {
            return $a->priority - $b->priority;
        });

        // Ordered parent rules based on priority.
        $parentrules = array();

        // They key is a rule that has children. Value is an array of child object rules.
        $parentmap = array();

        foreach ($rules as $rule) {

            if (!empty($rule->parent)) {
                $parentmap[$rule->parent][] = $rule;
            } else {
                // This is an ordered list of parent
                $parentrules[] = $rule;
            }
        }

        // Ordered list of rules for the table.
        $ordered = array();

        foreach ($parentrules as $rule) {
            $ordered[] = $rule;

            // If the rule found has an entry in the parentmap.
            if (array_key_exists($rule->id, $parentmap)) {

                // Append the array of children to the return result.
                $children = $parentmap[$rule->id];
                $ordered = array_merge($ordered, $children);

            }
        }

        return $ordered;

    }
}