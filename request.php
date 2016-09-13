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
 * Requests page in local_extension
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
global $CFG, $PAGE;

$PAGE->set_url(new moodle_url('/local/extension/request.php'));

$courseid  = optional_param('course', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

if (!empty($cmid)) {
    $cm = get_fast_modinfo($courseid)->get_cm($cmid);
    require_login($courseid, null, $cm);
    $context = context_module::instance($cmid);

} else if (!empty($courseid)) {
    require_login($courseid);
    $context = context_course::instance($courseid);

} else {
    require_login();
    $context = context_system::instance();

}

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('request_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

$PAGE->navbar->ignore_active();
$PAGE->navbar->add('Extension Status', new moodle_url('/local/extension/index.php'));
$PAGE->navbar->add('New Extension Request');

$searchback = optional_param('back', get_config('local_extension', 'searchback'), PARAM_INT);
$searchforward = optional_param('forward', get_config('local_extension', 'searchforward'), PARAM_INT);
$user = $USER->id;
$start = time() - $searchback * 24 * 60 * 60;
$end = time() + $searchforward * 24 * 60 * 60;

$options = array(
    'courseid' => $courseid,
    'moduleid' => $cmid,
    'requestid' => 0
);

$triggers = \local_extension\rule::load_all();
if (count($triggers) == 0) {
    // TODO no triggers are setup. Do not allow extensions.

    // Check if admin capability and send to trigger config page.
    echo $OUTPUT->header();
    echo html_writer::tag('p', 'No extension triggers defined.');
    echo $OUTPUT->footer();
    // exit;
}

list($handlers, $mods) = \local_extension\utility::get_activities($user, $start, $end, $options);

if (count($mods) == 0) {
    $obj = new stdClass();
    $obj->startrange = userdate($start);
    $obj->endrange = userdate($end);

    echo $OUTPUT->header();
    echo html_writer::tag('p', get_string('error_no_mods', 'local_extension', $obj));
    echo $OUTPUT->footer();
    exit;
}

$available = array();
$inprogress = array();

foreach ($mods as $mod) {

    // If a local cm object does not exist, then we can make a request for this module.
    if (empty($mod['localcm']->cm)) {
        $available[] = $mod;
    } else {
        $inprogress[] = $mod;
    }
}

$params = array(
    'available' => $available,
    'inprogress' => $inprogress,
    'course' => $courseid,
    'cmid' => $cmid,
    'context' => $context
);

$mform = new \local_extension\form\request(null, $params);

$usercontext = context_user::instance($USER->id);

if ($mform->is_cancelled()) {

    redirect(new moodle_url('/'));

} else if ($form = $mform->get_data()) {

    $now = time();

    $request = array(
        'userid' => $USER->id,
        'lastmodid' => $USER->id,
        'searchstart' => $start,
        'searchend' => $end,
        'timestamp' => $now,
        'lastmod' => $now,
        'messageid' => 0,
    );
    $request['id'] = $DB->insert_record('local_extension_request', $request);

    $comment = array(
        'request' => $request['id'],
        'userid' => $USER->id,
        'timestamp' => $now,
        'message' => $form->comment,
    );
    $comment['id'] = $DB->insert_record('local_extension_comment', $comment);

    foreach ($mods as $cmid => $mod) {

        $course = $mod['course'];
        $handler = $mod['handler'];

        $data = $handler->request_data($mform, $mod, $form);

        // If no data is present then an extension request date has not been specified.
        if ($data == '') {
            continue;
        }

        $cm = array(
            'request' => $request['id'],
            'userid' => $USER->id,
            'course' => $course->id,
            'timestamp' => $now,
            'cmid' => $cmid,
            'state' => 0,
            'data' => $data,
        );

        $cm['id'] = $DB->insert_record('local_extension_cm', $cm);
    }

    $draftitemid = file_get_submitted_draft_itemid('attachments');
    file_save_draft_area_files($draftitemid, $usercontext->id, 'local_extension', 'attachments', $request['id']);

    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'local_extension', 'attachments', $request['id']);
    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }

        $data = array(
            'requestid' => $request['id'],
            'timestamp' => $file->get_timecreated(),
            'filehash' => $file->get_pathnamehash(),
            'userid' => $file->get_userid(),
        );

        $DB->insert_record('local_extension_history_file', $data);
    }

    // Initiate the trigger/rule logic notifications and subscriptions, file attachment history.
    $req = \local_extension\request::from_id($request['id']);
    $req->process_triggers();

    $url = new moodle_url('/local/extension/status.php', array('id' => $req->requestid));
    redirect($url);
    die();
} else {
    $draftitemid = 0;
    file_prepare_draft_area($draftitemid, $usercontext->id, 'local_extension', 'attachments', 0);
}

echo $OUTPUT->header();

$contextlevels = array(
    CONTEXT_SYSTEM => "systemcontext",
    CONTEXT_COURSE => "coursecontext",
    CONTEXT_MODULE => "modulecontext",
);

$config = get_config('local_extension');

if (!empty($config->extensionpolicyrequest)) {
    $policy = $config->extensionpolicyrequest;
}

echo $policy;

foreach ($contextlevels as $contextlevel => $cfg) {
    if ($context->contextlevel == $contextlevel) {

        // If the configuration contexts are enabled then print the overall form to make multiple requests.
        if (!empty($config->$cfg)) {

            $mform->display();

        } else {

            // If the configuration contexts are disabled then provide links to make individual requests.
            foreach ($inprogress as $mod) {
                echo $mod['handler']->status_definition($mod);
            }

            if (!empty($available)) {
                echo html_writer::empty_tag('hr');
            }

            foreach ($available as $mod) {
                echo $mod['handler']->request_definition($mod);
            }

        }
    }
}

echo $OUTPUT->footer();
