<?php
// This file is part of Extension Plugin
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
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\navigation;

use context_system;
use local_extension\rule;
use local_extension\utility;
use moodle_url;
use navigation_node;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extension_navigation {
    public static function apply($nav) {
        global $PAGE, $USER;

        $context = $PAGE->context;
        $contextlevel = $context->contextlevel;
        $sitecontext = context_system::instance();

        // TODO add perms checks here. Maybe. Allow for students only?

        if (isloggedin() and !isguestuser()) {

            // If there are no triggers setup then we should not show these links at all.
            $triggers = rule::load_all();
            if (count($triggers) == 0) {
                return;
            }

            // This is a map of datatypes and their triggers. Useful to know if a trigger has been set for a datatype later.
            $datatypes = [];
            foreach ($triggers as $trigger) {
                $datatypes[$trigger->datatype][] = $trigger;
            }

            // General link in the navigation menu.
            $url = (new moodle_url('/local/extension/index.php'))->out();
            $nodename = get_string('requestextension_status', 'local_extension');
            $node = $nav->add($nodename, $url, null, null, 'local_extension');
            $node->showinflatnavigation = true;

            if ($contextlevel == CONTEXT_COURSE) {
                // If the user is not enrolled, do not provide an extension request link in the course/mod context.
                if (!is_enrolled($context, $USER->id)) {
                    return;
                }

                // Adding a nagivation string nested in the course that provides a count and status of the requests.
                $courseid = optional_param('id', 0, PARAM_INT);
                if (empty($courseid)) {
                    return;
                }

                $url = new moodle_url('/local/extension/request.php', ['course' => $courseid]);

                $coursenode = $nav->find($courseid, navigation_node::TYPE_COURSE);
                if (!empty($coursenode)) {
                    // MOODLE-1519 Workaround.
                    $requests = null; // \local_extension\utility::find_course_requests($courseid);

                    if (empty($requests)) {
                        // Display the request extension link.
                        $node = $coursenode->add(get_string('nav_request', 'local_extension'), $url);
                    } else {

                        $requestcount = utility::count_requests($courseid, $USER->id);

                        if ($requestcount > 1) {
                            $string = get_string('nav_course_request_plural', 'local_extension');
                        } else {
                            $string = get_string('nav_course_request', 'local_extension');
                        }

                        $requeststatus = utility::course_request_status($courseid, $USER->id);

                        $url = new moodle_url('/local/extension/index.php');
                        $node = $coursenode->add($requestcount . ' ' . $string . ' ' . $requeststatus, $url);
                    }
                }
            } else if ($contextlevel == CONTEXT_MODULE) {
                // Adding a navigation string nested in the course module that provides a status update.
                $id = optional_param('id', 0, PARAM_INT);

                if (empty($id)) {
                    return;
                }

                // If the user is not enrolled, do not provide an extension request link in the course/mod context.
                if (!is_enrolled($context, $USER->id)) {
                    return;
                }

                $modulenode = $nav->find($id, navigation_node::TYPE_ACTIVITY);
                if (!empty($modulenode)) {
                    $courseid = $PAGE->course->id;

                    list($request, $cm) = utility::find_module_requests($courseid, $id);

                    $modinfo = get_fast_modinfo($courseid);
                    $cmdata = $modinfo->cms[$id];

                    // Eg. assign, quiz.
                    $modname = $cmdata->modname;

                    // No triggers have been defined for this mod type. It will not show a request extension link.
                    if (!array_key_exists($modname, $datatypes)) {
                        return;
                    }

                    if (empty($cm)) {
                        // Display the request extension link.
                        $url = new moodle_url('/local/extension/request.php', ['course' => $courseid, 'cmid' => $id]);
                        $node = $modulenode->add(get_string('nav_request', 'local_extension'), $url);
                    } else {
                        // Display the request status for this module.
                        $url = new moodle_url('/local/extension/status.php', ['id' => $request->requestid]);

                        $localcm = $request->mods[$id]->localcm;

                        $result = \local_extension\state::instance()->get_state_result($localcm->get_stateid());

                        // The function block_nagivation->trim will truncate the navagation item to 25/50 characters.
                        $node = $modulenode->add(get_string('nav_status', 'local_extension', $result), $url);
                    }
                }
            }
        }
    }
}
