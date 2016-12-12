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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/coursecatlib.php');

define('DEFAULT_PAGE_SIZE', 20);

$page       = optional_param('page', 0, PARAM_INT);
$perpage    = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT);
$categoryid = optional_param('catid', 0, PARAM_INT);
$courseid   = optional_param('id', 0, PARAM_INT);
$stateid    = optional_param('state', 0, PARAM_INT);
$search     = optional_param('search', '', PARAM_RAW); // Make sure it is processed with p() or s() when sending to output!
$faculty    = optional_param('faculty', '', PARAM_RAW); // Make sure it is processed with p() or s() when sending to output!

$PAGE->set_url('/local/extension/index.php', array(
    'page'      => $page,
    'perpage'   => $perpage,
    'catid'     => $categoryid,
    'state'     => $stateid,
    'id'        => $courseid,
    'search'    => s($search),
    'faculty'   => s($faculty),
));

require_login();

if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
} else {
    $courseid = SITEID;
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_user::instance($USER->id, MUST_EXIST);
}

if ($categoryid) {
    $categorycontext = context_coursecat::instance($categoryid);
} else {
    $categorycontext = $context;
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

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('page_h2_summary', 'local_extension'));

// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = new moodle_url('/local/extension/index.php', array(
    'id'      => $courseid,
    'catid'   => $categoryid,
    'search'  => s($search),
    'faculty' => s($faculty),
));

// New filter functionality, searching and listing of requests.
echo $renderer->render_index_search_controls($context, $categoryid, $courseid, $stateid, $baseurl, $search, $faculty);

$table = new \local_extension\local\table\index($baseurl);

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

if ($stateid != 0) {
    $wheres[] = "lcm.state = :stateid";
    $params = array_merge($params, array('stateid' => $stateid), $params);
}

$showuseridentityfields = explode(',', $CFG->showuseridentity);
$ufields = array('username', 'email', 'city', 'country', 'lang', 'timezone', 'maildisplay', 'idnumber');
$mainuserfields = user_picture::fields('u', $ufields);

$viewallrequests = false;
if (has_capability('local/extension:viewallrequests', $categorycontext)) {
    $viewallrequests = true;
}

if (has_capability('local/extension:viewallrequests', $context)) {
    $viewallrequests = true;
}

if ($viewallrequests) {

    // This query obtains ALL local cm requests, with the possible filters: coursename, username, activityname, status.
    $select = "SELECT lcm.id AS lcmid,
                      lcm.name AS activity,
                      lcm.length,
                      lcm.state,
                      lcm.data as newduedate,
                      r.id AS rid,
                      r.lastmodid,
                      r.lastmod,
                      r.timestamp,
                      r.userid,
                      u.idnumber,
                      c.fullname AS coursename,
                      c.id AS courseid,
                      $mainuserfields";

    $joins[] = "FROM {local_extension_cm} lcm";

    $joins[] = "JOIN {local_extension_request} r ON r.id = lcm.request";
    $joins[] = "JOIN {course} c ON c.id = lcm.course";
    $joins[] = "JOIN {user} u ON u.id = r.userid";

} else {
    // Filtering the subscriptions to only those that belong to the $USER.
    // If a rule has been triggered this will grant access to individuals to modify/view the requests.
    $wheres[] = "s.userid = :subuserid";
    $params = array_merge($params, array('subuserid' => $USER->id), $params);

    // This query obtains ALL local cm requests, that the $USER has a subscription to with the possible filters:
    // coursename, username, activityname, status.
    $select = "SELECT lcm.id AS lcmid,
                      lcm.name AS activity,
                      lcm.length,
                      lcm.state,
                      lcm.data as newduedate,
                      r.id AS rid,
                      r.lastmodid,
                      r.lastmod,
                      r.timestamp,
                      r.userid,
                      u.idnumber,
                      c.fullname AS coursename,
                      c.id AS courseid,
                      $mainuserfields";

    $joins[] = "FROM {local_extension_cm} lcm";

    // Sometimes invalid trigger setup will assign multiple subscription states. This queries the distinct possibilities.
    $joins[] = "JOIN
                    (
                    SELECT DISTINCT localcmid,
                    userid
                    FROM {local_extension_subscription}
                    ) s
                ON s.localcmid = lcm.id";

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

    $wherestr  = "(". $DB->sql_like($fullname, ':search1', false, false);
    $wherestr .= " OR ". $DB->sql_like('c.fullname', ':search2', false, false);

    if (in_array('idnumber', $showuseridentityfields)) {
        $wherestr .= " OR ". $DB->sql_like('u.idnumber', ':search3', false, false);
        $params['search3'] = "%$search%";
    }

    $wherestr .= " OR ". $DB->sql_like('lcm.name', ':search4', false, false) .") ";

    $wheres[] = $wherestr;

    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search4'] = "%$search%";
}

if (!empty($faculty)) {
    $wheres[] = $DB->sql_like('c.shortname', ':faculty', false, false);
    $params['faculty'] = "%$faculty%";
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
    $sort = " ORDER BY " . $table->get_sql_sort();
} else {
    $sort = "";
}

$matchcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

$table->pagesize($perpage, $matchcount);

$requestlist = $DB->get_records_sql("$select $from $where $sort", $params, $table->get_page_start(), $table->get_page_size());

if ($requestlist) {

    // Example: 'Wed, 26 Oct 2016 3:12PM'.
    $format = '%a, %e %b %Y %l:%M %p';

    foreach ($requestlist as $request) {
        $usercontext = context_user::instance($request->userid);

        if ($piclink = ($USER->id == $request->userid ||
            has_capability('moodle/user:viewdetails', $context) ||
            has_capability('moodle/user:viewdetails', $usercontext))) {

            $moodleurl = new moodle_url('/user/view.php', array('id' => $request->userid, 'course' => $request->courseid));
            $link = html_writer::link($moodleurl, fullname($request));

            $profilelink = html_writer::tag('b', $link);
        } else {
            $profilelink = html_writer::tag('b', fullname($request));
        }

        $lastmoduser = core_user::get_user($request->lastmodid);

        $requesturl = new moodle_url('/local/extension/status.php', array('id' => $request->rid));
        $requestlink = html_writer::link($requesturl, $request->rid);

        $requestlength = \local_extension\utility::calculate_length($request->length);

        $delta = $request->lastmod - time();
        $show = format_time($delta);
        $num = strtok($show, ' ');
        $unit = strtok(' ');
        $show = "$num $unit";
        $lastmodstring = get_string('ago', 'message', $show);
        $lastmod  = html_writer::start_div('lastmodby');
        $lastmod .= html_writer::tag('span', $lastmodstring);
        $lastmod .= html_writer::end_div();

        $cmstate = \local_extension\state::instance()->get_state_name($request->state);

        $data = array(
            $requestlink,
            $OUTPUT->user_picture($request, array('size' => 35, 'courseid' => $request->courseid)),
            $profilelink,
            userdate($request->timestamp, $format),
            html_writer::div($requestlength, 'lastmodby'),
            userdate($request->newduedate, $format),
            html_writer::link($requesturl, $request->coursename),
            html_writer::link($requesturl, $request->activity),
            html_writer::div($cmstate, 'lastmodby'),
            $lastmod,
        );

        if (in_array('idnumber', $showuseridentityfields)) {
            $moodleurl = new moodle_url('/user/view.php', array('id' => $request->userid, 'course' => $request->courseid));
            $link = html_writer::link($moodleurl, $request->idnumber);

            $div = html_writer::div($link, 'lastmodby');

            array_splice($data, 3, 0, $div);
        }

        $table->add_data($data);
    }

    $table->finish_html();
}

if ($courseid == SITEID) {
    $courseid = 0;
}

$params = array(
    'course'  => $courseid,
);

$url = new moodle_url("/local/extension/request.php", $params);

echo $OUTPUT->single_button($url, get_string('button_request_extension', 'local_extension'));

echo $OUTPUT->footer();
