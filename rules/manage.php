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
 * Manage the adapter triggers
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_extension\rule;
use local_extension\utility;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$url = new moodle_url('/local/extension/rules/manage.php');
$PAGE->set_url($url);

$context = context_system::instance();
require_login();

\admin_externalpage_setup('local_extension_settings_rules');

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('rules_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

/* @var $renderer local_extension_renderer IDE hinting */
$renderer = $PAGE->get_renderer('local_extension');

$rules = rule::load_all();
$ordered = utility::rule_tree($rules);

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('page_heading_manage', 'local_extension'));

// Display a table of all triggers when no id is present.
$table = new \local_extension\local\table\rules();
$table->set_data($ordered);
echo $table->finish_output();

echo html_writer::empty_tag('br');

$editurl = new moodle_url("/local/extension/rules/edit.php");
$mods = \local_extension\plugininfo\extension::get_enabled_request();
echo $renderer->render_manage_new_rule($mods, $editurl);

echo $OUTPUT->footer();
