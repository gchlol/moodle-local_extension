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
 * Requests page in local_extension. Prorviding a filter and search for requests.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/coursecatlib.php');

define('DEFAULT_PAGE_SIZE', 20);

$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT);
$categoryid = optional_param('catid', 0, PARAM_INT);
$courseid   = optional_param('id', 0, PARAM_INT);
$contextid  = optional_param('contextid', 0, PARAM_INT);
$search     = optional_param('search', '', PARAM_RAW); // Make sure it is processed with p() or s() when sending to output!

$PAGE->set_url('/local/extension/index.php', array(
    'page' => $page,
    'perpage' => $perpage,
    'contextid' => $contextid,
    'catid' => $categoryid,
    'id' => $courseid,
    'search' => $search,
));

// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = new moodle_url('/local/extension/index.php', array(
    'id' => $courseid,
    'catid' => $categoryid,
    'search' => s($search)
));

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSE) {
        print_error('invalidcontext');
    }
    $course = $DB->get_record('course', array('id' => $context->instanceid), '*', MUST_EXIST);
} else if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
} else {
    $courseid = 1;
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
}

require_login($course);

$systemcontext = context_system::instance();

$isfrontpage = ($course->id == SITEID);
$frontpagectx = context_course::instance(SITEID);

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

/* @var \local_extension_renderer $renderer */
$renderer = $PAGE->get_renderer('local_extension');

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('page_heading_summary', 'local_extension'));

/*
 * New filter functionality, searching and listing of requests.
 */

// Print a filter settings items across the top of the page.
$controlstable = new html_table();
$controlstable->attributes['class'] = 'controls';
$controlstable->cellspacing = 0;
$controlstable->data[] = new html_table_row();

// Display a list of categories.
if (has_capability('moodle/category:manage', context_system::instance())) {
    $categorylist = array();
    $categorylist[0] = coursecat::get(0)->get_formatted_name();
    $categorylist += coursecat::make_categories_list();

    $popupurl = new moodle_url('/local/extension/index.php');

    $select = new single_select($popupurl, 'catid', $categorylist, $categoryid, null, 'requestform');

    $html = html_writer::span('Categories', '', array('id' => 'categories'));
    $html .= $OUTPUT->render($select);

    $categorycell = new html_table_cell();
    $categorycell->attributes['class'] = 'right';
    $categorycell->text = $html;

    $controlstable->data[0]->cells[] = $categorycell;
}

// Display a list of enrolled courses to filter by.
if ($mycourses = enrol_get_my_courses()) {
    $courselist = array();

    $courselist['1'] = 'All';

    foreach ($mycourses as $mycourse) {
        $coursecontext = context_course::instance($mycourse->id);
        $courselist[$mycourse->id] = format_string($mycourse->fullname, true, array('context' => $coursecontext));
    }
    if (has_capability('moodle/site:viewparticipants', $systemcontext)) {
        unset($courselist[SITEID]);
        $courselist = array(SITEID => format_string($SITE->fullname, true, array('context' => $systemcontext))) + $courselist;
    }

    $popupurl = new moodle_url('/local/extension/index.php', array(
        'catid' => $categoryid
    ));



    $select = new single_select($popupurl, 'id', $courselist, $courseid, null, 'requestform');

    $html  = html_writer::span(get_string('mycourses'), '', array('id' => 'courses'));
    $html .= $OUTPUT->render($select);
    $controlstable->data[0]->cells[] = $html;
}

// Display a list of all courses to filter by
if (has_capability('moodle/category:manage', context_system::instance())) {
    $options = array();

    if (!empty($categoryid)) {
        $courses = coursecat::get($categoryid)->get_courses(array('recursive' => true));
    } else {
        $courses = coursecat::get(0)->get_courses(array('recursive' => true));
    }

    $options['1'] = 'All';

    foreach ($courses as $course) {
        $options[$course->id] = $course->fullname;
    }

    $popupurl = new moodle_url('/local/extension/index.php', array(
        'catid' => $categoryid
    ));

    $select = new single_select($popupurl, 'id', $options, $courseid, null, 'requestform');

    $html = html_writer::span('Courses', '', array('id' => 'courses'));
    $html .= $OUTPUT->render($select);

    $categorycell = new html_table_cell();
    $categorycell->attributes['class'] = 'right';
    $categorycell->text = $html;

    $controlstable->data[0]->cells[] = $categorycell;
}

$searchcoursecell = new html_table_cell();
$searchcoursecell->attributes['class'] = 'right';
$searchcoursecell->text = $renderer->render_search_course($baseurl, 'id', $search);
$controlstable->data[0]->cells[] = $searchcoursecell;

echo html_writer::table($controlstable);

// Write the flexible_table with the current details.
$tablecolumns = array();
$tablecolumns[] = 'userpic';
$tablecolumns[] = 'fullname';
$tablecolumns[] = 'requestid';
$tablecolumns[] = 'timestamp';
$tablecolumns[] = 'coursename';
$tablecolumns[] = 'instance';
$tablecolumns[] = 'lastmod';

$tableheaders = array();
$tableheaders[] = get_string('userpic');
$tableheaders[] = get_string('fullnameuser');
$tableheaders[] = 'Request ID';
$tableheaders[] = 'Requested date';
$tableheaders[] = 'Course';
$tableheaders[] = 'Activity';
$tableheaders[] = 'Last updated by';

$table = new flexible_table('usertable');

$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl->out());

$table->no_sorting('userpic');
$table->sortable('requestid');

$table->set_attribute('cellspacing', '0');

$table->setup();

$joins = array();
$wheres = array();
$params = array();

if ($courseid != 1) {
    $wheres[] = "lcm.course = :courseid";
    $params = array_merge($params, array('courseid' => $courseid), $params);
}

if ($categoryid != 0) {
    $wheres[] = "c.category = :categoryid";
    $params = array_merge($params, array('categoryid' => $categoryid), $params);
}

$mainuserfields = user_picture::fields('u', array('username', 'email', 'city', 'country', 'lang', 'timezone', 'maildisplay'));

// This query obtains ALL requests, that is filtered by category and course.
$select = "SELECT r.id as requestid,
                  lcm.id as cmid,
                  r.timestamp,
                  r.lastmod,
                  r.lastmodid,
                  r.userid,
                  cm.module as module,
                  md.name as handler,
                  cm.instance,
                  c.fullname as coursename,
                  $mainuserfields";

$joins[] = "FROM {local_extension_request} r";
$joins[] = "JOIN {user} u ON u.id = r.userid";
$joins[] = "JOIN {local_extension_cm} lcm ON lcm.request = r.id";
$joins[] = "JOIN {course_modules} cm ON cm.id = lcm.cmid";
$joins[] = "JOIN {modules} md ON md.id = cm.module";
$joins[] = "JOIN {course} c ON c.id = lcm.course";

$from = implode("\n", $joins);
if ($wheres) {
    $where = "WHERE " . implode(" AND ", $wheres);
} else {
    $where = "";
}

$totalcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

if (!empty($search)) {
    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $wheres[] = "(". $DB->sql_like($fullname, ':search1', false, false) .
        " OR ". $DB->sql_like('c.fullname', ':search2', false, false) .
        " OR ". $DB->sql_like('md.name', ':search3', false, false) .") ";
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

list($twhere, $tparams) = $table->get_sql_where();
if ($twhere) {
    $wheres[] = $twhere;
    $params = array_merge($params, $tparams);
}

$from = implode("\n", $joins);
if ($wheres) {
    $where = "WHERE " . implode(" AND ", $wheres);
} else {
    $where = "";
}

if ($table->get_sql_sort()) {
    $sort = ' ORDER BY '.$table->get_sql_sort();
} else {
    $sort = '';
}

$matchcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

$table->pagesize($perpage, $matchcount);

$requestlist = $DB->get_records_sql("$select $from $where $sort", $params, $table->get_page_start(), $table->get_page_size());

if ($requestlist) {

    // Obtain lists of each module => instance mapping for fetching the titles of the module that was requested.
    $modulemap = array();

    foreach ($requestlist as $request) {
        $modulemap[$request->handler][] = $request->instance;
    }

    $instancenames = array();

    foreach ($modulemap as $handler => $values) {
        list($instanceids, $params) = $DB->get_in_or_equal($values);

        $sql = "SELECT *
                  FROM {".$handler."}
                 WHERE id $instanceids";

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $item) {
            $instancenames[$item->id] = $item->name;
        }

        $rs->close();
    }

    foreach ($requestlist as $request) {
        $usercontext = context_user::instance($request->userid);

        if ($piclink = ($USER->id == $request->userid || has_capability('moodle/user:viewdetails', $context) || has_capability('moodle/user:viewdetails', $usercontext))) {
            $profilelink = '<strong><a href="'.$CFG->wwwroot.'/user/view.php?id='.$request->userid.'&amp;course='.$course->id.'">'.fullname($request).'</a></strong>';
        } else {
            $profilelink = '<strong>'.fullname($request).'</strong>';
        }

        $lastmoduser = core_user::get_user($request->lastmodid);

        $requesturl = new moodle_url('/local/extension/status.php', array('id' => $request->requestid));
        $requestlink = html_writer::link($requesturl, $request->requestid);

        $lastmod = userdate($request->lastmod) . " " . fullname($lastmoduser);

        $data = array(
            $OUTPUT->user_picture($request, array('size' => 35, 'courseid' => $course->id)),
            $profilelink,
            $requestlink,
            userdate($request->timestamp),
            $request->coursename,
            $instancenames[$request->instance],
            $lastmod,
        );

        $table->add_data($data);
    }

    $table->finish_html();
}

$url = new moodle_url("/local/extension/request.php");
echo $OUTPUT->single_button($url, get_string('button_request_extension', 'local_extension'));

echo $OUTPUT->footer();
