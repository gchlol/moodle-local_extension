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

use global_navigation;
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
    public static function is_valid_logged_user() {
        if (!isloggedin()) {
            return false;
        }

        if (isguestuser()) {
            return false;
        }

        return true;
    }

    public static function apply(global_navigation $globalnavigation) {
        $extensionnavigation = new extension_navigation($globalnavigation);
        $extensionnavigation->add_nodes();
    }

    /** @var global_navigation */
    private $globalnavigation;

    public function __construct(global_navigation $globalnavigation) {
        $this->globalnavigation = $globalnavigation;
    }

    private function add_nodes() {
        global $PAGE;

        if (!self::is_valid_logged_user()) {
            return;
        }

        if (!rule::has_rules()) {
            return;
        }

        $this->add_node_in_main_navigation();

        switch ($PAGE->context->contextlevel) {
            case CONTEXT_COURSE:
                $this->add_node_in_course();
                break;
            case CONTEXT_MODULE:
                $this->add_node_in_module();
                break;
        }
    }

    private function add_node_in_course() {
        global $PAGE, $USER, $COURSE;

        if (!is_enrolled($PAGE->context, $USER->id)) {
            return;
        }

        $coursenode = $this->globalnavigation->find($COURSE->id, navigation_node::TYPE_COURSE);
        if (empty($coursenode)) {
            debugging("Cannot find course node for course id: {$COURSE->id}");
            return;
        }

        $requests = utility::find_course_requests($COURSE->id);

        if (empty($requests)) {
            $url = new moodle_url('/local/extension/request.php', ['course' => $COURSE->id]);
            $coursenode->add(get_string('nav_request', 'local_extension'), $url);
        } else {
            $requestcount = utility::count_requests($COURSE->id, $USER->id);
            if ($requestcount > 1) {
                $label = get_string('nav_course_request_plural', 'local_extension');
            } else {
                $label = get_string('nav_course_request', 'local_extension');
            }

            $url = new moodle_url('/local/extension/index.php');
            $coursenode->add("{$requestcount} {$label}", $url);
        }
    }

    private function add_node_in_module() {
        global $PAGE, $USER;

        // Adding a navigation string nested in the course module that provides a status update.
        $id = optional_param('id', 0, PARAM_INT);

        if (empty($id)) {
            return;
        }

        // If the user is not enrolled, do not provide an extension request link in the course/mod context.
        if (!is_enrolled($PAGE->context, $USER->id)) {
            return;
        }

        $modulenode = $this->globalnavigation->find($id, navigation_node::TYPE_ACTIVITY);
        if (!empty($modulenode)) {
            $courseid = $PAGE->course->id;

            list($request, $cm) = utility::find_module_requests($courseid, $id);

            $modinfo = get_fast_modinfo($courseid);
            $cmdata = $modinfo->cms[$id];

            // Eg. assign, quiz.
            $modname = $cmdata->modname;

            // No triggers have been defined for this mod type. It will not show a request extension link.
            if (!rule::has_rules($modname)) {
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

    private function add_node_in_main_navigation() {
        $url = (new moodle_url('/local/extension/index.php'))->out();
        $nodename = get_string('requestextension_status', 'local_extension');
        $node = $this->globalnavigation->add($nodename, $url, null, null, 'local_extension');
        $node->showinflatnavigation = true;
    }
}
