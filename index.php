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

$PAGE->set_url(new moodle_url('/local/extension/index.php'));

// TODO context could be user, course or module.
$context = context_user::instance($USER->id);

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$table = generate_table();
$data = generate_table_data($table);
$url = new moodle_url("/local/extension/request.php");

$renderer = $PAGE->get_renderer('local_extension');

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('summary_page_heading', 'local_extension'));
echo $renderer->render_extension_summary_table($table, $data);
echo html_writer::empty_tag('br');
echo $OUTPUT->single_button($url, get_string('requestextension', 'local_extension'));
echo $OUTPUT->footer();