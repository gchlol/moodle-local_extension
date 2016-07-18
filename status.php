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
global $PAGE, $USER;

require_login(false);

// TODO add perms checks here.

$requestid = required_param('id', PARAM_INTEGER);

$url = new moodle_url('/local/extension/status.php', array('id' => $requestid));
$PAGE->set_url($url);
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('status_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$request = \local_extension\utility::cache_get_request($requestid);
$renderer = $PAGE->get_renderer('local_extension');
$mform = new \local_extension\form\update(null, array('user' => $OUTPUT->user_picture($USER), 'request' => $request, 'renderer' => $renderer));

if ($form = $mform->get_data()) {
    $requestid = $form->id;
    $comment = $form->commentarea;
    $request->add_comment($USER, $comment);
    redirect($url);
} else {
    $mform->set_data(array('id' => $requestid));
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();