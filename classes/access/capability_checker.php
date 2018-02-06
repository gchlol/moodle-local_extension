<?php
// This file is part of Extension Plugin
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
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\access;

use context_course;
use context_coursecat;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_checker {
    const CAPABILITY_VIEW_ALL_REQUESTS = 'local/extension:viewallrequests';

    const CAPABILITY_ACCESS_ALL_COURSE_REQUESTS = 'local/extension:accessallcourserequests';

    const CAPABILITY_EXPORT_REQUESTS_CSV = 'local/extension:exportrequestscsv';

    public static function can_view_all_requests($categoryid = null, $defaultcontext = null) {
        if ($categoryid) {
            $categorycontext = context_coursecat::instance($categoryid);
            if (has_capability(self::CAPABILITY_VIEW_ALL_REQUESTS, $categorycontext)) {
                return true;
            }
        }

        if (is_null($defaultcontext)) {
            $defaultcontext = context_system::instance();
        }

        if (has_capability(self::CAPABILITY_VIEW_ALL_REQUESTS, $defaultcontext)) {
            return true;
        }

        return false;
    }

    private static function can_access_all_course_requests($courseid) {
        $context = context_course::instance($courseid);
        $hascapability = has_capability(self::CAPABILITY_ACCESS_ALL_COURSE_REQUESTS, $context);
        return $hascapability;
    }

    public static function can_force_change_status($courseid) {
        $context = context_course::instance($courseid);
        $hascapability = has_capability('local/extension:modifyrequeststatus', $context);
        return $hascapability;
    }

    public static function can_export_csv() {
        $context = context_system::instance();
        $hascapability = has_capability(self::CAPABILITY_ACCESS_ALL_COURSE_REQUESTS, $context);
        return $hascapability;
    }

    public static function get_courses_ids_with_all_access_to_all_requests() {
        $mycourses = enrol_get_my_courses(['id'], 'id ASC');
        $withaccess = [];
        foreach ($mycourses as $mycourse) {
            if (self::can_access_all_course_requests($mycourse->id)) {
                $withaccess[] = (int)$mycourse->id;
            }
        }
        return $withaccess;
    }

    public static function can_view_request($request) {
        global $USER;

        if (self::can_view_all_requests()) {
            return true;
        }

        foreach ($request->cms as $cm) {
            $courseid = $cm->cm->course;
            if (self::can_access_all_course_requests($courseid)) {
                return true;
            }
        }

        // Is user subscribed?
        if (array_key_exists($USER->id, $request->users)) {
            return true;
        }

        return false;
    }
}
