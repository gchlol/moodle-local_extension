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
 * Request class
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

/**
 * Request class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {

    public $cms = array();
    public $comments = array();
    public $users = array();

    /**
     * Obtain request data for the renderer.
     *
     * @param int $reqid An id for a request.
     * @return request $req A request data object.
     */
    public static function from_id($reqid) {
        global $DB;

        $req = new request();
        $userids = array();
        $userrecords = array();

        $req->request  = $DB->get_records('local_extension_request', array('id' => $reqid));
        $req->cms      = $DB->get_records('local_extension_cm', array('request' => $reqid));
        $req->comments = $DB->get_records('local_extension_comment', array('request' => $reqid));
        // TODO need to sort cms by date and comments by date.

        // Obtain a unique list of userids that have been commenting.
        foreach ($req->comments as $comment) {
            $userids[$comment->userid] = $comment->userid;
        }

        // Fetch the users
        // TODO change this to single call using get_in_or_equal .
        foreach ($userids as $uid) {
            $userrecords[$uid] = $DB->get_record('user', array('id' => $uid), \user_picture::fields());
        }

        $req->users = $userrecords;

        return $req;
    }

}
