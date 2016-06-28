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
require_once('locallib.php');
global $CFG, $PAGE;

require_login(false);

$PAGE->set_url(new moodle_url('/local/extension/request.php'));

$PAGE->set_context(context_system::instance());
// $PAGE->set_pagelayout('standard');
$PAGE->set_pagelayout('base');

$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('request_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

echo $OUTPUT->header();

$user = $USER->id;
// TODO get this from params
$start = time() - 7 * 24 * 60 * 60;
$end = time() + 7 * 24 * 60 * 60;
$course = 0;

list($handlers, $mods) = local_extension_get_activities($user, $start, $end, $course);

$mform = new \local_extension\form\request(null, array('mods' => $mods));

if ($mform->is_cancelled()) {

    // TODO Do what?
    redirect($returnurl);

} else if ($form = $mform->get_data()) {
    $id = $form->id;

    // TODO create the request record.
    // TODO create the sub-extend records
    // TODO create the first comment record

    // redirect to status page.
}

$mform->display();

// var_dump($mods);
echo $OUTPUT->footer();

