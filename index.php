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
global $CFG, $PAGE;

$PAGE->set_url(new moodle_url('/local/extension/index.php'));

require_login();

// TODO context could be user, course or module.
$context = \context_user::instance($USER->id);

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

$renderer = $PAGE->get_renderer('local_extension');

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('page_heading_summary', 'local_extension'));

$table = \local_extension\table::generate_index_table();
// TODO Replace 0 with $USER->id to filter the requests.
// As a active request table must be created.
$data = \local_extension\table::generate_index_data($table, 0);
echo $renderer->render_extension_summary_table($table, $data);

echo html_writer::empty_tag('br');

$url = new moodle_url("/local/extension/request.php");
echo $OUTPUT->single_button($url, get_string('button_request_extension', 'local_extension'));

echo $OUTPUT->footer();