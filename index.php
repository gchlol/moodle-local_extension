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
 * Requests page in local_extension. Providing a filter and search for requests.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_extension\access\capability_checker;
use local_extension\form\preferences_form;
use local_extension\preferences;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/coursecatlib.php');

$defaultcategoryid = get_config('local_extension', 'defaultcategory');

$categoryid = optional_param('catid', $defaultcategoryid, PARAM_INT);
$courseid   = optional_param('id', 0, PARAM_INT);
$stateid    = optional_param('state', 0, PARAM_INT);
$search     = optional_param('search', '', PARAM_RAW); // Make sure it is processed with p() or s() when sending to output!
$faculty    = optional_param('faculty', '', PARAM_RAW); // Make sure it is processed with p() or s() when sending to output!
$download   = optional_param('download', '', PARAM_ALPHA);

require_login(null, false);

$PAGE->set_url('/local/extension/index.php', []);

if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
} else {
    $courseid = SITEID;
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_user::instance($USER->id, MUST_EXIST);
}

$checkcontext = $categoryid ? context_coursecat::instance($categoryid) : null;
$viewallrequests = capability_checker::can_view_all_requests($checkcontext);

if (!$viewallrequests) {
    // The user cannot view all requests or select the categories.
    $categoryid = 0;

    // Prevent standard user from downloading.
    $download = false;
}

$systemcontext = context_system::instance();
$isfrontpage = ($course->id == SITEID);
$frontpagectx = context_course::instance(SITEID);

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('page_heading_index', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('breadcrumb_nav_index', 'local_extension'), new moodle_url('/local/extension/index.php'));

/* @var \local_extension_renderer $renderer IDE hinting */
$renderer = $PAGE->get_renderer('local_extension');

// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = new moodle_url('/local/extension/index.php', array(
    'id'      => $courseid,
    'catid'   => $categoryid,
    'state'   => $stateid,
    'search'  => s($search),
    'faculty' => s($faculty),
));

// If the user can view all requests, display the administrator table.
if ($viewallrequests) {
    $table = new \local_extension\local\table\requests\administrator($baseurl, null, (bool)$download);
} else {
    $table = new \local_extension\local\table\requests\student($baseurl, null, (bool)$download);
}

$table->generate_query($categoryid, $courseid, $stateid, $search, $faculty);

$table->is_downloading($download, 'AES_export', 'AES_export');

if (!$table->is_downloading()) {
    echo $OUTPUT->header();

    // New filter functionality, searching and listing of requests.
    echo $renderer->render_index_search_controls($context, $categoryid, $courseid, $stateid, $baseurl, $search, $faculty);

    if (((new preferences)->get(preferences::EXPORT_CSV)) == '1') {
        $url = clone $baseurl;
        $url->param('download', 'csv');
        echo html_writer::div(
            $OUTPUT->single_button($url, get_string('export_csv', 'local_extension')),
            'local_extension_option_buttons'
        );
    }

    echo html_writer::div(
        $OUTPUT->single_button(
            '/local/extension/preferences.php',
            get_string('preferences', 'local_extension')
        ),
        'local_extension_option_buttons'
    );

    echo html_writer::tag('h2', get_string('page_h2_summary', 'local_extension'));
}

$table->out(30, false);

if (!$table->is_downloading()) {

    echo html_writer::empty_tag('br');

    if ($courseid == SITEID) {
        $courseid = 0;
    }

    $url = new moodle_url("/local/extension/request.php", ['course'  => $courseid]);

    echo $OUTPUT->single_button($url, get_string('button_request_extension', 'local_extension'));

    echo $OUTPUT->footer();
}
