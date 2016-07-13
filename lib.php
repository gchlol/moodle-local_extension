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
 * lib.php
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the link to start a request
 *
 * @param global_navigation $nav Navigation
 */
function local_extension_extends_navigation(global_navigation $nav) {
    global $PAGE, $USER;

    $context = $PAGE->context;
    $contextlevel = $context->contextlevel;
    $sitecontext = context_system::instance();

    // TODO add perms checks here. Maybe.

    if (isloggedin() and !isguestuser()) {
        // General link in the navigation menu.
        $url = new moodle_url('/local/extension/index.php');
        $node = $nav->add(get_string('requestextension_status', 'local_extension'), $url->out(), null, null, 'local_extension');

        if ($contextlevel == CONTEXT_COURSE) {

            $courseid = optional_param('id', 0, PARAM_INT);

            $url = new moodle_url('/local/extension/request.php', array('course' => $courseid));

            $coursenode = $nav->find($courseid, navigation_node::TYPE_COURSE);
            if (!empty($coursenode)) {
                $requests = local_extension_find_request($courseid, $USER->id);

                if (empty($requests)) {
                    // Display the request extension link.
                    $node = $coursenode->add(get_string('requestextension', 'local_extension'), $url);
                } else {
                    // Display the request status for this module.
                    foreach ($requests as $request) {
                        $url = new moodle_url('/local/extension/status.php', array('id' => $request->requestid));
                        $node = $coursenode->add('sdfgs', $url);
                    }
                }
            }

        } else if ($contextlevel == CONTEXT_MODULE) {
            $id = optional_param('id', 0, PARAM_INT);
            $courseid = optional_param('course', 0, PARAM_INT);
            $cmid = optional_param('cmid', 0, PARAM_INT);

            if (empty($cmid)) {
                $courseid = $PAGE->course->id;
                $cmid = $id;
            }

            $coursenode = $nav->find($cmid, navigation_node::TYPE_ACTIVITY);
            if (!empty($coursenode)) {
                list($request, $cm) = local_extension_find_request($courseid, $USER->id, $cmid);

                if (empty($cm)) {
                    // Display the request extension link.
                    $url = new moodle_url('/local/extension/request.php', array('course' => $courseid, 'cmid' => $cmid));
                    $node = $coursenode->add(get_string('requestextension', 'local_extension'), $url);
                } else {
                    // Display the request status for this module.
                    $url = new moodle_url('/local/extension/status.php', array('id' => $request->requestid));
                    $handler = $request->mods[$cmid]['handler'];
                    $status = $handler->get_status_name($cm->status);
                    $node = $coursenode->add('Extension: ' . $status, $url);
                }
            }

        }

    }

}

/**
 * Serves the extension files.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function local_extension_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_USER) {
        return false;
    }

    // Ensure the filearea is the one used by this plugin.
    if ($filearea !== 'attachments') {
        return false;
    }

    require_login($course, $true, $cm);

    // TODO add perms checks here.

    $itemid = array_shift($args);
    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_extension', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true);
}

/**
 * Returns an array of all requests from the cache.
 *
 * @return request[] An array of requests.
 */

function local_extension_cache_get_requests() {
    global $DB;

    $sql = "SELECT r.id
              FROM {local_extension_request} r";

    $requestids = $DB->get_fieldset_sql($sql);

    $cache = cache::make('local_extension', 'requests');
    return $cache->get_many($requestids);
}

function local_extension_find_request($courseid, $userid, $moduleid = 0) {
    global $USER;

    $requests = local_extension_cache_get_requests();

    if (empty($moduleid)) {
        $matchedrequests = array();
        // Return matching requests for a course.
        foreach ($requests as $request) {
            foreach ($request->cms as $cm) {
                if ($courseid == $cm->course && $userid == $USER->id) {
                    $matchedrequests[$cm->request] = $request;
                    break;
                }
            }
        }
        return $matchedrequests;

    } else {
        // Return a matching course module, eg. assignment, quiz.
        foreach ($requests as $request) {
            foreach ($request->cms as $cm) {
                if ($courseid == $cm->course && $moduleid == $cm->cmid && $userid == $USER->id) {
                    return array($request, $cm);
                }
            }
        }
    }
}