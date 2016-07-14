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
 * Utility class
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Utility class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utility {
    /**
     * Generates the basic requirements the status page table.
     *
     * @return flexible_table
     */
    public static function generate_table() {
        global $PAGE;

        $headers = array(
                get_string('table_header_request', 'local_extension'),
                get_string('table_header_items', 'local_extension'),
                get_string('table_header_requestdate', 'local_extension'),
                get_string('table_header_statushead', 'local_extension'),
        );

        $columns = array('request', 'date', 'items', 'status');

        $table = new \flexible_table('local_extension_summary');
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
     * @param integer $userid
     * @return request[] An array of request objects
     */
     public static function generate_table_data($table, $userid = 0) {
        global $DB;

        // TODO tablelib orderby column options? should we enable this?

        if (!empty($userid)) {
            $where = " WHERE cm.userid = ? ";
            $params = array('userid' => $userid);
        } else {
            $where = '';
            $params = array();
        }

        $sql = "SELECT r.id,
        r.timestamp,
        COUNT(cm.request)
        FROM {local_extension_request} r
        LEFT JOIN {local_extension_cm} cm
        ON cm.request = r.id
        $where
        GROUP BY r.id
        ORDER BY r.timestamp ASC";

        $requests = $DB->get_records_sql($sql, $params);

        return $requests;
    }

    public static function course_request_status($courseid, $userid) {
        global $DB;

    }

    /**
     * Returns the number of requests the user has for a specific course.
     * @param interger $courseid
     * @param interger $userid
     * @return integer count
     */
     public static function count_requests($courseid, $userid) {
        global $DB;

        $sql = "SELECT count(cm.request)
              FROM {local_extension_cm} cm
             WHERE userid = :userid
               AND course = :courseid";

        $params = array('userid' => $userid, 'courseid' => $courseid);

        $record = $DB->get_record_sql($sql, $params);

        if (!empty($record)) {
            return $record->count;
        } else {
            return 0;
        }
    }

    /**
     * Obtains a request from the cache.
     *
     * @param integer $requestid
     * @return request A request object.
     */
     public static function cache_get_request($requestid) {
        $cache = \cache::make('local_extension', 'requests');
        return $cache->get($requestid);
    }

    /**
     * Returns an array of all requests from the cache for the user specified.
     *
     * @return request[] An array of requests.
     */
     public static function cache_get_requests($userid = 0) {
        global $DB;

        if (!empty($userid)) {
            $where = " WHERE r.userid = ? ";
            $params = array('userid' => $userid);
        } else {
            $where = '';
            $params = array();
        }

        $sql = "SELECT r.id
        FROM {local_extension_request} r
        $where";

        $requestids = $DB->get_fieldset_sql($sql, $params);

        $cache = \cache::make('local_extension', 'requests');
        return $cache->get_many($requestids);
    }

    /**
     * When a request has been modified this will invalidate the cache for that requestid.
     *
     * @param integer $requestid
     */
    public static function cache_invalidate_request($requestid) {
        $cache = \cache::make('local_extension', 'requests');
        $cache->delete($requestid);
    }

    /**
     * Obtains the requests for the current user. Filterable by courseid and moduleid.
     *
     * @param integer $courseid
     * @param integer $moduleid
     * @return request[]|request[]|unknown[]
     */
    public static function find_request($courseid, $moduleid = 0) {
        global $USER, $CFG;

        $requests = self::cache_get_requests($USER->id);

        if (empty($moduleid)) {
            $matchedrequests = array();
            // Return matching requests for a course.
            foreach ($requests as $request) {
                foreach ($request->cms as $cm) {
                    if ($courseid == $cm->course) {
                        $matchedrequests[$cm->request] = $request;
                        break;
                    }
                }
            }
            return $matchedrequests;

        } else {
            // Return a matching course module, eg. assignment, quiz.
            foreach ($requests as $request) {
                foreach ($request->cms as $cm) {
                    if ($courseid == $cm->course && $moduleid == $cm->cmid) {
                        return array($request, $cm);
                    }
                }
            }
        }
    }

}