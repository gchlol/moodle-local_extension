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
 * Display the form for a student to request additional time on an existing extension.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
global $CFG, $PAGE;

$requestid     = required_param('id', PARAM_INT);
$cmid          = required_param('cmid', PARAM_INT);
$courseid      = required_param('courseid', PARAM_INT);

$PAGE->set_url(new moodle_url('/local/extension/request_additional.php'), [
    'id'       => $requestid,
    'cmid'     => $cmid,
    'courseid' => $courseid,
]);

$cm = get_fast_modinfo($courseid)->get_cm($cmid);
require_login($courseid, null, $cm);

$request = \local_extension\request::from_id($requestid);

$context = context_module::instance($cmid);
$PAGE->set_context($context);

$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('page_heading_additional_request', 'local_extension'));

$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

$requestuser = core_user::get_user($request->request->userid);
$pageurl = new moodle_url('/local/extension/status.php', array('id' => $request->requestid));
$obj = ['id' => $requestid, 'name' => fullname($requestuser)];

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('breadcrumb_nav_index', 'local_extension'), new moodle_url('/local/extension/index.php'));
$PAGE->navbar->add(get_string('breadcrumb_nav_status', 'local_extension', $obj), $pageurl);
$PAGE->navbar->add(get_string('breadcrumb_nav_additional', 'local_extension'));

$params = [
    'request' => $request,
    'cmid'    => $cmid,
];

$mform = new \local_extension\form\request_additional(null, $params);

if ($mform->is_cancelled()) {
    $statusurl = new moodle_url('/local/extension/status.php', array('id' => $requestid));
    redirect($statusurl);

} else if ($data = $mform->get_data()) {

}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();


