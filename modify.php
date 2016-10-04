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
 * Status page in local_extension
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_extension\utility;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
global $PAGE, $USER;

require_login(true);

$requestid = required_param('id', PARAM_INT);
$courseid = required_param('course', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

// $cm = get_fast_modinfo($courseid)->get_cm($cmid);
// require_login($courseid, null, $cm);

$request = utility::cache_get_request($requestid);

// Item $request->user is an array of $userid=>$userobj associated to this request, eg. those that are subscribed, and the user.
// The list of subscribed users populated each time the request object is generated.
// The request object is invalidated and regenerated after each comment, attachment added, or rule triggered.

// Checking if the current user is not part of the request.
if (!array_key_exists($USER->id, $request->users)) {
    $context = context_module::instance($cmid);
    // Admin users will have this capability, or anyone that was subscribed.
    if (!has_capability('local/extension:viewallrequests', $context)) {
        die();
    }

} else {
    // The user is part of the request, lets check their access.
    $access = $request->get_user_access($USER->id, $request->cms[$cmid]->cm->id);
    if ($access != \local_extension\rule::RULE_ACTION_APPROVE &&
        $access != \local_extension\rule::RULE_ACTION_FORCEAPPROVE) {
        die();
    }
}


$url = new moodle_url('/local/extension/modify.php', array('id' => $requestid, 'course' => $courseid, 'cmid' => $cmid));
$PAGE->set_url($url);

// TODO context could be user, course or module.
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('page_heading_index', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

$renderer = $PAGE->get_renderer('local_extension');

$mod = $request->mods[$cmid];
$course = $mod['course'];
$cm = $mod['cm'];

$context = \context_module::instance($cmid);

$assign = new \assign($context, $cm, $course);
$instance = $assign->get_instance();

$params = array(
    'request' => $request,
    'cmid' => $cmid,
    'instance' => $assign->get_instance(),
);

$requestuser = core_user::get_user($request->request->userid);

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('breadcrumb_nav_index', 'local_extension'), new moodle_url('/local/extension/index.php'));
$PAGE->navbar->add(get_string('breadcrumb_nav_modify', 'local_extension'), $url);

$obj = array('id' => $requestid, 'name' => fullname($requestuser));

$pageurl = new moodle_url('/local/extension/status.php', array('id' => $request->requestid));
$PAGE->navbar->add(get_string('breadcrumb_nav_status', 'local_extension', $obj), $pageurl);

$mform = new \local_extension\form\modify(null, $params);

if ($form = $mform->get_data()) {

    // TODO Edge cases with lowering the length beyond set triggers. Deal with changes / triggers.
    $cm = $request->cms[$cmid];
    $event = $request->mods[$cmid]['event'];
    $course  = $request->mods[$cmid]['course'];

    $due = 'due' . $cmid;

    $originaldate = $cm->cm->data;
    $newdate = $form->$due;

    $delta = $newdate - $originaldate;

    $show = format_time($delta);
    $num = strtok($show, ' ');
    $unit = strtok(' ');
    $show = "$num $unit";

    // Prepend -+ signs to indicate a difference in length.
    $sign = $delta < 0 ? '-' : '+';

    $obj = new stdClass();
    $obj->course = $course->fullname;
    $obj->event = $event->name;
    $obj->original = userdate($originaldate);
    $obj->new = userdate($newdate);
    $obj->diff = $sign . $show;

    $delta = $event->timestart - $newdate;
    $show = format_time($delta);
    $num = strtok($show, ' ');
    $unit = strtok(' ');
    $show = "$num $unit";

    $obj->length = $show;

    $datestring = get_string('page_modify_comment', 'local_extension', $obj);

    $cm->cm->data = $newdate;
    $cm->cm->length = $newdate - $event->timestart;
    $cm->update_data();

    $notifycontent = array();
    $notifycontent[] = $request->add_comment($USER, $datestring);

    // If the date has changed, we need to run the triggers to see if we alert new subscribers.
    $request->process_triggers();

    // Process the triggers before sending the notifications. New subscribers exist.
    $request->notify_subscribers($notifycontent, $USER->id);

    $request->get_data_cache()->delete($request->requestid);
    $statusurl = new moodle_url('/local/extension/status.php', array('id' => $requestid));

    // TODO Run triggers, update subscriptions.
    redirect($statusurl);

} else {
    $data = new stdClass();
    $data->id = $requestid;
    $data->cmid = $cmid;
    $data->userid = $request->request->userid;
    $data->course = $courseid;
    $mform->set_data($data);
}

echo $OUTPUT->header();

// TODO echo $renderer->display_modify_heading();.

$mform->display();

echo $OUTPUT->footer();
