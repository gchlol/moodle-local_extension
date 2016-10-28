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

defined('MOODLE_INTERNAL') || die();

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
     * @param int|\stdClass $userid Userid or user object
     * @param int $start  Start of search period
     * @param int $end End of search period
     * @param array $options Optional arguments.
     * @return array An array of candidates. array($mods, $events)
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

        // A list of courses that the $request->userid is enrolled in, this will be passed to the next filters.
        $courses = enrol_get_users_courses($userid);

        // Get the events matching our criteria.
        list($courses, $group, $user2) = calendar_set_filters($courses);

        $allevents = calendar_get_events($start, $end, array($userid), $groups, $courses);

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
                $localcm = cm::from_requestid($cm->id, $requestid);

                // A requestid has been specified, lets list the available local_cms.

                // No local_extension_cm found, we won't need to provide an event.
                if (empty($localcm->cm)) {
                    continue;
                }

            } else {
                // Try and obtain a request associated to this user for a course module.
                $localcm = cm::from_userid($cm->id, $userid);
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
     * Sends a notification email, or queues them if the request object has more than one local cm/mod item.
     *
     * @param \local_extension\request $request
     * @param string $subject
     * @param string $content
     * @param \stdClass $userfrom
     * @param \stdClass $userto
     */
    public static function send_trigger_email(\local_extension\request $request, $subject, $content, $userfrom, $userto) {
        $emaildisable = get_config('local_extension', 'emaildisable');
        if ($emaildisable == true) {
            return;
        }

        // Email threading.

        // The base messageid.
        $rootid = self::get_email_message_id($request->requestid, $userto->id);

        $userfrom->customheaders = array(
            'Message-ID: ' . $rootid,

            // Headers to help prevent auto-responders.
            'Precedence: Bulk',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
        );

        if ($request->request->messageid != 0) {
            $inputid = $request->requestid . $request->request->messageid;
            $messageid = self::get_email_message_id($inputid, $userto->id);

            // This post is a reply, so add reply header (RFC 2822).
            $userfrom->customheaders[] = "In-Reply-To: $rootid";
            $userfrom->customheaders[] = "References: $rootid";
            $userfrom->customheaders[] = 'Message-ID: ' . $messageid;
        }

        // MS Outlook / Office uses poorly documented and non standard headers, including
        // Thread-Topic which overrides the Subject and shouldn't contain Re: or Fwd: etc.

        $userfrom->customheaders[] = "Thread-Topic: $subject";
        $userfrom->customheaders[] = "Thread-Index: " . substr($rootid, 1, 28);

        $message = new \stdClass();
        $message->component         = 'local_extension';
        $message->name              = 'status';
        $message->userfrom          = $userfrom;
        $message->userto            = $userto;
        $message->subject           = $subject;
        $message->fullmessage       = html_to_text($content);;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = $content;
        $message->smallmessage      = '';
        $message->notification      = 1;
        message_send($message);
    }

    /**
     * Create a message-id string to use in the custom headers of extension request notification emails
     *
     * message-id is used by email clients to identify emails and to nest conversations
     *
     * @param int $requestid The ID of the request.
     * @param int $usertoid The ID of the user being notified
     * @return string A unique message-id
     */
    public static function get_email_message_id($requestid, $usertoid) {
        return self::generate_email_messageid(hash('sha256', $requestid . 'to' . $usertoid));
    }

    /**
     * Generate a unique email Message-ID using the moodle domain and install path
     *
     * @param string $localpart An optional unique message id prefix.
     * @return string The formatted ID ready for appending to the email headers.
     */
    public static function generate_email_messageid($localpart = null) {
        global $CFG;
        $urlinfo = parse_url($CFG->wwwroot);

        // TODO REMOVE AS THIS IS USED FOR DEMO.
        if (!empty($CFG->wwwroots)) {
            $urlinfo = parse_url($CFG->wwwroots[0]);
        }

        $base = '@' . $urlinfo['host'];
        // If multiple moodles are on the same domain we want to tell them
        // apart so we add the install path to the local part. This means
        // that the id local part should never contain a / character so
        // we can correctly parse the id to reassemble the wwwroot.
        if (isset($urlinfo['path'])) {
            $base = $urlinfo['path'] . $base;
        }
        if (empty($localpart)) {
            $localpart = uniqid('', true);
        }

        // Because we may have an option /installpath suffix to the local part
        // of the id we need to escape any / chars which are in the $localpart.
        $localpart = str_replace('/', '%2F', $localpart);

        return '<' . $localpart . $base . '>';
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
     *
     * @param int $courseid
     * @param int $userid
     * @return int count
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
     * @param int $requestid
     * @return request A request object.
     */
    public static function cache_get_request($requestid) {
        $cache = \cache::make('local_extension', 'requests');
        return $cache->get($requestid);
    }

    /**
     * Returns an array of all requests from the cache for the user specified.
     *
     * @param int $userid
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
     * @param int $courseid
     * @param int $moduleid
     * @return array
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
     * Sorted based on the priority and grouped with parents.
     *
     * @param \local_extension\rule[] $rules
     * @return array
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
                // This is an ordered list of parents.
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

    /**
     * Returns a tree structure of the rules.
     * Nested child rules can be accessed via $rule->children
     *
     * @param \local_extension\rule[] $rules
     * @param int|number $parent
     * @return rule[] Tree structure.
     */
    public static function rule_tree(array $rules, $parent = 0) {
        $branch = array();

        // Sort the rule nodes based on priority.
        usort($rules, function($a, $b) {
            return $a->priority - $b->priority;
        });

        foreach ($rules as $element) {
            if ($element->parent == $parent) {
                $children = self::rule_tree($rules, $element->id);

                // Sort the child nodes based on priority.
                usort($children, function($a, $b) {
                    return $a->priority - $b->priority;
                });

                if ($children) {
                    $element->children = $children;

                    foreach ($children as $child) {
                        $child->parentrule = $element;
                    }
                }

                $branch[] = $element;
            }
        }

        return $branch;
    }

    /**
     * Returns a branch of rules with the given parent id.
     *
     * Useful for recursive deletion.
     *
     * @param array $rules
     * @param int $id
     * @return boolean
     */
    public static function rule_tree_branch(array $rules, $id) {

        foreach ($rules as $rule) {

            if (!empty($rule->children)) {
                $ret = self::rule_tree_branch($rule->children, $id);
            }

            // We have matched the id, return the branch with its child nodes.
            if ($rule->id == $id) {
                return $rule;
            }
        }

        if (!empty($ret)) {
            return $ret;
        }

        return false;
    }

    /**
     * Returns an array of ids that are possible candidates for being a parent item.
     *
     * @param \local_extension\rule[] $rules
     * @param int $id The id that will not be added, nor children added.
     * @param array $idlist A growing list of ids that can be possible parent items.
     * @return array An associated array of id=>name for parents.
     */
    public static function rule_tree_check_children(array $rules, $id, $idlist = null) {

        if (empty($idlist)) {
            $idlist = array();
        }

        // Sort the rules based on priority.
        usort($rules, function($a, $b) {
            return $a->priority - $b->priority;
        });

        foreach ($rules as $rule) {

            // Prevent this branch and all child nodes from being a parent.
            if ($rule->id == $id) {
                continue;
            }

            if (!empty($rule->children)) {
                $children = self::rule_tree_check_children($rule->children, $id, $idlist);
                $idlist = $idlist + $children;
            }

            $idlist[$rule->id] = $rule->name;
        }

        return array_reverse($idlist, true);
    }

    /**
     * Returns a string with the time format as days, hours, minutes.
     *
     * @param int $seconds
     * @return string
     */
    public static function calculate_length($seconds) {
        $seconds = abs($seconds);

        $dtf = new \DateTime('@0');
        $dtt = new \DateTime("@$seconds");
        $diff = $dtf->diff($dtt);

        $fmt = '';

        if ($diff->d) {
            $fmt = '%a day' . ($diff->d > 1 ? 's' : '');
        }

        if ($diff->h) {

            $fmt .= (empty($fmt) ? '' : ', ') . '%h hour' . ($diff->h > 1 ? 's' : '');
        }

        if ($diff->i) {
            $fmt .= (empty($fmt) ? '' : ', ') . '%i minute' . ($diff->i > 1 ? 's' : '');
        }

        return $diff->format($fmt);
    }

}
