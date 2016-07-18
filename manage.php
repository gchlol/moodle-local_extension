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

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$PAGE->set_url(new moodle_url('/local/extension/manage.php'));

$context = \context_system::instance();
require_login();

\admin_externalpage_setup('local_extension_rules');

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('rules_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$mform = new \local_extension\form\rule(null, null);

if ($mform->is_cancelled()) {

    //redirect(new moodle_url('/'));

} else if ($form = $mform->get_data()) {

    $url = new moodle_url('/local/extension/manage.php');
    redirect($url);
    die;
}

/*
 * Roadmap.
 *
 * - Table(s) with a list of the rules, based on context? adpater? overall?.
 * - Ability to re-order them, group them?
 *
 */

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
