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
    'page'      => $page,
    'perpage'   => $perpage,
    'contextid' => $contextid,
    'catid'     => $categoryid,
    'id'        => $courseid,
    'search'    => $search,
));

// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = new moodle_url('/local/extension/index.php', array(
    'id'     => $courseid,
    'catid'  => $categoryid,
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
    $courseid = SITEID;
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_coursecat::instance($course->id, MUST_EXIST);
}

require_login($course);

$systemcontext = context_system::instance();

$isfrontpage = ($course->id == SITEID);
$frontpagectx = context_course::instance(SITEID);

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('page_heading_index', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');
$PAGE->add_body_class('local_extension');

/* @var \local_extension_renderer $renderer */
$renderer = $PAGE->get_renderer('local_extension');

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('page_h2_summary', 'local_extension'));

// New filter functionality, searching and listing of requests.
echo $renderer->render_index_search_controls($context, $categoryid, $courseid, $baseurl, $search);

// Write the flexible_table with the current details.
$tablecolumns = array();
$tablecolumns[] = 'userpic';
$tablecolumns[] = 'fullname';
$tablecolumns[] = 'rid';
$tablecolumns[] = 'timestamp';
$tablecolumns[] = 'coursename';
$tablecolumns[] = 'activity';
$tablecolumns[] = 'lastmod';

$tableheaders = array();
$tableheaders[] = get_string('userpic');
$tableheaders[] = get_string('fullnameuser');
$tableheaders[] = get_string('table_header_index_requestid', 'local_extension');
$tableheaders[] = get_string('table_header_index_requestdate', 'local_extension');
$tableheaders[] = get_string('table_header_index_course', 'local_extension');
$tableheaders[] = get_string('table_header_index_activity', 'local_extension');
$tableheaders[] = get_string('table_header_index_lastmod', 'local_extension');

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

$viewallrequests = has_capability('local/extension:viewallrequests', $context);

if ($viewallrequests) {

    // This query obtains ALL local cm requests, with the possible filters: coursename, username, activityname.
    $select = "SELECT lcm.id AS lcmid,
                      lcm.name AS activity,
                      r.id AS rid,
                      r.lastmodid,
                      r.lastmod,
                      r.timestamp,
                      r.userid,
                      c.fullname AS coursename,
                      $mainuserfields";

    $joins[] = "FROM {local_extension_cm} lcm";

    $joins[] = "JOIN {local_extension_request} r ON r.id = lcm.request";
    $joins[] = "JOIN {course} c ON c.id = lcm.course";
    $joins[] = "JOIN {user} u ON u.id = r.userid";

} else {
    // Filtering the subscriptions to only those that belong to the $USER. If a rule has been triggered this will grant access to individuals to modify/view the requests.
    $wheres[] = "s.userid = :subuserid";
    $params = array_merge($params, array('subuserid' => $USER->id), $params);

    // This query obtains ALL local cm requests, that the $USER has a subscription to, with the possible filters: coursename, username, activityname.
    $select = "SELECT lcm.id AS lcmid,
                      lcm.name AS activity,
                      r.id AS rid,
                      r.lastmodid,
                      r.lastmod,
                      r.timestamp,
                      r.userid,
                      c.fullname AS coursename,
                      $mainuserfields";

    $joins[] = "FROM {local_extension_cm} lcm";

    $joins[] = "JOIN {local_extension_subscription} s ON s.localcmid = lcm.id";
    $joins[] = "JOIN {local_extension_request} r ON r.id = lcm.request";
    $joins[] = "JOIN {course} c ON c.id = lcm.course";
    $joins[] = "JOIN {user} u ON u.id = r.userid";

}

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
        " OR ". $DB->sql_like('lcm.name', ':search3', false, false) .") ";
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

    foreach ($requestlist as $request) {
        $usercontext = context_user::instance($request->userid);

        if ($piclink = ($USER->id == $request->userid || has_capability('moodle/user:viewdetails', $context) || has_capability('moodle/user:viewdetails', $usercontext))) {
            $profilelink = '<strong><a href="'.$CFG->wwwroot.'/user/view.php?id='.$request->userid.'&amp;course='.$course->id.'">'.fullname($request).'</a></strong>';
        } else {
            $profilelink = '<strong>'.fullname($request).'</strong>';
        }

        $lastmoduser = core_user::get_user($request->lastmodid);

        $requesturl = new moodle_url('/local/extension/status.php', array('id' => $request->rid));
        $requestlink = html_writer::link($requesturl, $request->rid);

        $lastmod  = html_writer::start_div('lastmodby');
        $lastmod .= html_writer::tag('span', userdate($request->lastmod));
        $lastmod .= html_writer::empty_tag('br');
        $lastmod .= html_writer::tag('span', fullname($lastmoduser));
        $lastmod .= html_writer::end_div();

        $data = array(
            $OUTPUT->user_picture($request, array('size' => 35, 'courseid' => $course->id)),
            $profilelink,
            $requestlink,
            userdate($request->timestamp),
            $request->coursename,
            $request->activity,
            $lastmod,
        );

        $table->add_data($data);
    }

    $table->finish_html();
}

$url = new moodle_url("/local/extension/request.php");
echo $OUTPUT->single_button($url, get_string('button_request_extension', 'local_extension'));

echo $OUTPUT->footer();
