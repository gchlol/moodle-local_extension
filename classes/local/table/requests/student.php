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
 * The student table for requests.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\local\table\requests;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

use local_extension\access\capability_checker;
use moodle_url;
use user_picture;

/**
 * The student table for requests.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student extends request_list {
    /**
     * Constructor
     * @param moodle_url $baseurl
     * @param string|null $id to be used by the table, autogenerated if null.
     */
    public function __construct($baseurl, $id = null, $downloading = false) {
        $id = (is_null($id) ? self::$autoid++ : $id);
        parent::__construct($baseurl, 'local_extension' . $id, $downloading);

        $this->show_download_buttons_at([]);
    }

    /**
     * Consumes the input parameters and sets the table SQL.
     *
     * @param integer $categoryid
     * @param integer $courseid
     * @param integer $stateid
     * @param string $search
     * @param string $faculty
     */
    public function generate_query($categoryid, $courseid, $stateid, $search, $faculty) {
        global $USER;

        $userfieldsapi = \core_user\fields::for_userpic()->including(
            'username', 'email', 'city', 'country', 'lang', 'timezone', 'maildisplay', 'idnumber');
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        // This query obtains ALL local cm requests, that the $USER has a subscription to with the possible filters:
        // coursename, username, activityname, status.
        $this->select = "lcm.id AS lcmid,
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
                         $userfields";

        $this->params = [];
        $this->joins = [];

        $this->joins[] = "{local_extension_cm} lcm";

        // Sometimes invalid trigger setup will assign multiple subscription states.
        // This queries the distinct possibilities.
        // Filtering the subscriptions to only those that belong to the $USER.
        // If a rule has been triggered this will grant access to individuals to modify/view the requests.
        $this->joins[] = "LEFT JOIN
                              (
                                  SELECT DISTINCT localcmid
                                  FROM {local_extension_subscription}
                                  WHERE userid = :subuserid
                              ) s
                          ON s.localcmid = lcm.id";
        $this->params['subuserid'] = $USER->id;

        $this->joins[] = "JOIN {local_extension_request} r ON r.id = lcm.request";
        $this->joins[] = "JOIN {course} c ON c.id = lcm.course";
        $this->joins[] = "JOIN {user} u ON u.id = r.userid";

        // Show only records with subscription or that belong to a course with view capability.
        $viewcoursesids = capability_checker::get_courses_ids_with_all_access_to_all_requests();
        $where = 's.localcmid IS NOT NULL';
        if (count($viewcoursesids) > 0) {
            $viewcoursesids = implode(',', $viewcoursesids);
            $where = "({$where} OR lcm.course IN ({$viewcoursesids}))";
        }
        $this->where[] = $where;

        if ($courseid != 1) {
            $this->where[] = "lcm.course = :courseid";
            $this->params = array_merge($this->params, ['courseid' => $courseid], $this->params);
        }

        if ($categoryid != 0) {
            $this->where[] = "c.category = :categoryid";
            $this->params = array_merge($this->params, ['categoryid' => $categoryid], $this->params);
        }

        if ($stateid != 0) {
            $this->where[] = "lcm.state = :stateid";
            $this->params = array_merge($this->params, ['stateid' => $stateid], $this->params);
        }

        $this->apply_faculty($faculty);

        $this->apply_search($search);

        $this->from = implode("\n", $this->joins);

        $this->where = implode(" AND ", $this->where);

        $this->set_sql($this->select, $this->from, $this->where, $this->params);
    }
}
