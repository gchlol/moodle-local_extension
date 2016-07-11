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

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/tablelib.php');

/**
 * Returns a list of candidate dates for activities
 *
 * @param user $user Userid or user object
 * @param timestamp $start  Start of search period
 * @param timestamp $end End of search period
 * @param course $course Optional courseid
 * @return An array of candidates.
 *
 */
function local_extension_get_activities($user, $start, $end, $course = 0) {

    global $DB;

    $dates = array();

    $mods = \local_extension\plugininfo\extension::get_enabled_request();

    // To be efficient we do a single search through the calendar and then
    // filter these events down to one's that can handle extensions.

    $groups = null;
    $courses = null;

    // Get the events matching our criteria.
    list($courses, $group, $user2) = calendar_set_filters(array());

    $allevents = calendar_get_events($start, $end, array($user), $groups, true);

    $events = array();
    $courses = array();

    foreach ($allevents as $id => $event) {

        $modtype = $event->modulename;

        // First filter to only activities that have an extension plugin.
        if (!isset($mods[$modtype])) {
            continue;
        }

        $handler = $mods[$modtype];

        if (!$cm = get_coursemodule_from_instance($event->modulename, $event->instance)) {
            continue;
        }

        if (!\core_availability\info_module::is_user_visible($cm, 0, false)) {
            continue;
        }

        // Now give the handler a chance to filter, for instance an activity
        // could have a open, due and close, but it may only really care about
        // the due date.
        if (!$handler->is_candidate($event, $cm)) {
            continue;
        }

        $courseid = $cm->course;
        if (!isset($courses[$courseid])) {
            $courses[$courseid] = $DB->get_record('course', array('id' => $courseid));
        }

        // TODO if an activity already has a extension request associated with it
        // then handle this in some way. Possibly filter, or perhaps show it but
        // direct the student to their previous request.

        $events[$cm->id] = array(
            'event' => $event,
            'cm' => $cm,
            'course' => $courses[$courseid],
            'handler' => $handler,
        );
    }

    return array($mods, $events);
}

/**
 * Sends a status email to the student.
 * TODO Implement sending to course coordinators with the capabilitiy and role to grant extensions.
 *
 * @param integer $requestid
 */
function send_status_email($requestid) {
    global $DB, $USER, $PAGE;

    $request = \local_extension\request::from_id($requestid);

    $user = $DB->get_record('user', array('id' => $request->request->userid));

    $renderer = $PAGE->get_renderer('local_extension');
    $text = $renderer->render_extension_email($request);

    $message = new stdClass();
    $message->component         = 'local_extension';
    $message->name              = 'status';
    $message->userfrom          = $USER;
    $message->userto            = $user;
    $message->subject           = "Extension request for " . \fullname($user);
    $message->fullmessage       = $text;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml   = '';
    $message->smallmessage      = '';
    $message->notification      = 1;
    message_send($message);
}

/**
 * Generates the basic requirements the status page table.
 *
 * @return flexible_table
 */
function generate_table() {
    global $PAGE;

    $headers = array(
        get_string('table_header_request', 'local_extension'),
        get_string('table_header_items', 'local_extension'),
        get_string('table_header_requestdate', 'local_extension'),
        get_string('table_header_statushead', 'local_extension'),
    );

    $columns = array('request', 'date', 'items', 'status');

    $table = new flexible_table('local_extension_summary');
    $table->define_columns($columns);
    $table->define_headers($headers);

    $table->define_baseurl($PAGE->url);
    $table->set_attribute('id', 'local_extension_table');
    $table->set_attribute('class', 'generaltable admintable');
    $table->setup();

    return $table;
}

/**
 * Generates the data required for the status page table.
 * @param flexible_table $table
 * @return request[] An array of request objects
 */
function generate_table_data($table) {
    global $DB, $USER;

    $data = array();

    // TODO tablelib orderby column options? should we enable this?

    $sql = "SELECT r.id,
                   r.timestamp,
                   COUNT(cm.request)
              FROM {local_extension_request} r
         LEFT JOIN {local_extension_cm} cm
                ON cm.request = r.id
          GROUP BY r.id
          ORDER BY r.timestamp ASC";

    $requests = $DB->get_records_sql($sql);

    return $requests;
}
