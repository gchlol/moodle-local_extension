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

require_once('../../config.php');
require_once('locallib.php');
global $CFG, $PAGE, $USER;

require_login(false);

// TODO add perms checks here.

$requestid = required_param('id', PARAM_INTEGER);

$PAGE->set_url(new moodle_url('/local/extension/status.php', array('id' => $requestid)));
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('status_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$mform = new \local_extension\form\comment(null, array('user' => $OUTPUT->user_picture($USER)));

if ($form = $mform->get_data()) {
    $requestid = $form->id;
    $comment = $form->commentarea;
    $format = FORMAT_PLAIN;

    \local_extension\request::add_comment($requestid, $USER, $comment, $format);

    // We load the request data after adding a comment to see it!
    $request = \local_extension\request::from_id($requestid);
} else {
    $request = \local_extension\request::from_id($requestid);
    $mform->set_data(array('id' => $requestid));
}

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('local_extension');
echo $renderer->render_extension_status($request);
$mform->display();
echo $OUTPUT->footer();

// Just for testing.
//send_status_email($requestid);