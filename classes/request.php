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

    /** @var integer The local_extension_request database object */
    public $requestid = null;

    /** @var integer The local_extension_request database object */
    public $request = array();

    /** @var integer */
    public $cms = array();

    /** @var integer An array of comment objects from the request id */
    public $comments = array();

    /** @var integer An array of user objects with the available fields user_picture::fields  */
    public $users = array();

    /** @var integer An array of attached files that exist for this request id */
    public $files = array();

    /** @var array An array of mods that are used */
    public $mods = array();

    /** @var integer New request. */
    const STATUS_NEW = 0;

    /** @var integer Denied request. */
    const STATUS_DENIED = 1;

    /** @var integer Approved request. */
    const STATUS_APPROVED = 2;

    /** @var integer Reopened request. */
    const STATUS_REOPENED = 3;

    /** @var integer Cancelled request. */
    const STATUS_CANCEL = 4;

    /**
     * Request object constructor.
     * @param integer $requestid An optional variable to identify the request.
     */
    public function __construct($requestid = null) {
        $this->requestid = $requestid;
    }

    /**
     * Loads data into the object
     * @throws coding_exception
     */
    public function load() {
        global $DB;

        if (empty($this->requestid)) {
            throw coding_exception('No request id');
        }

        $requestid = $this->requestid;

        $this->request  = $DB->get_record('local_extension_request', array('id' => $requestid), '*', MUST_EXIST);
        $this->cms      = $DB->get_records('local_extension_cm', array('request' => $requestid), 'id ASC', 'cmid,course,data,handler,id,request,status,userid');
        $this->comments = $DB->get_records('local_extension_comment', array('request' => $requestid), 'timestamp ASC');

        $request = $this->request;

        list($handlers, $mods) = local_extension_get_activities($request->userid, $request->searchstart, $request->searchend);
        $this->mods = $mods;

        $userids = array($request->userid => $request->userid);

        // TODO need to sort cms by date and comments by date.

        // Obtain a unique list of userids that have been commenting.
        foreach ($this->comments as $comment) {
            $userids[$comment->userid] = $comment->userid;
        }

        // Fetch the users.
        list($uids, $params) = $DB->get_in_or_equal($userids);
        $userfields = \user_picture::fields();
        $sql = "SELECT $userfields
                  FROM {user}
                 WHERE id $uids";
        $this->users = $DB->get_records_sql($sql, $params);

        $this->files = $this->fetch_attachments($requestid);
    }

    /**
     * Obtain request data for the renderer.
     *
     * @param integer $requestid An id for a request.
     * @return request $req A request data object.
     */
    public static function from_id($requestid) {
        $request = new request($requestid);
        $request->load();
        return $request;
    }

    /**
     * Fetch the list of attached files for the request id.
     *
     * @param integer $requestid An id for a request.
     * @return array An array of file objects.
     */
    public function fetch_attachments($requestid) {
        global $USER;

        $context = \context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_extension', 'attachments', $requestid);

        return $files;
    }

    /**
     * Adds a comment to the request
     *
     * @param stdClass $from The user that has commented.
     * @param string $comment The comment itself.
     */
    public function add_comment($from, $comment) {
        global $DB;

        $comment = (object)array(
            'request'       => $this->requestid,
            'userid'        => $from->id,
            'timestamp'     => \time(),
            'message'       => $comment,
        );
        $DB->insert_record('local_extension_comment', $comment);
    }

    /**
     * Loads comments from the database into the request object.
     */
    public function load_comments() {
        global $DB;

        if (!empty($this->requestid)) {
            $this->comments = $DB->get_records('local_extension_comment', array('request' => $this->requestid));
        }
    }

    /**
     * Sets the state of this request.
     *
     * @param stdClass $cm The request local cm object.
     * @param integer $status The status.
     */
    public function set_status($cm, $status) {
        global $DB;

        $cm->status = $status;
        $DB->update_record('local_extension_cm', $cm);
    }

    /**
     * Query the $cm and get the next available states.
     *
     * @param stdClass $cm The request cm object.
     * @return array An array of available states.
     */
    public function get_next_status($cm) {
        switch ($cm->status) {
            case self::STATUS_NEW:
                return array(self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_CANCEL);
            case self::STATUS_DENIED:
                return array(self::STATUS_REOPENED, self::STATUS_CANCEL);
            case self::STATUS_APPROVED:
                return array(self::STATUS_CANCEL);
            case self::STATUS_REOPENED:
                return array(self::STATUS_APPROVED, self::STATUS_CANCEL, self::STATUS_DENIED);
            case self::STATUS_CANCEL:
                return array();
            default:
                return array();
        }
    }

    /**
     * Returns a human readable state name.
     *
     * @param string $status one of the state constants like STATUS_NEW.
     * @throws coding_exception
     * @return string the human-readable status name.
     */
    public function get_status_name($status) {
        switch ($status) {
            case self::STATUS_NEW:
                return \get_string('state_statusnew',      'local_extension');
            case self::STATUS_DENIED:
                return \get_string('state_statusdenied',   'local_extension');
            case self::STATUS_APPROVED:
                return \get_string('state_statusapproved', 'local_extension');
            case self::STATUS_REOPENED:
                return \get_string('state_statusreopened', 'local_extension');
            case self::STATUS_REOPENED:
                return \get_string('state_statuscancel',   'local_extension');
            default:
                throw new coding_exception('Unknown request attempt state.');
        }
    }

    /**
     * Gets the local extension cm data.
     *
     * @param integer $cmid
     * @return stdClass $cm local extension cm data.
     */
    public function get_local_cm($cmid) {
        foreach ($this->cms as $localid => $cm) {
            if ($cm->cmid == $cmid) {
                return $cm;
            }
        }
        return null;
    }

    /**
     * Returns cms grouped by the course they are associated with.
     * @return array An array of courses and their mods.
     */
    public function get_cms_by_course() {
        $cms = array();

        foreach ($this->cms as $id => $cm) {
            $cms[$cm->course][] = $cm;
        }

        return $cms;
    }

    /**
     * Returns mods grouped by the course they are associated with.
     * @return array An array of courses and their mods.
     */
    public function get_mods_by_course() {
        $mods = array();

        foreach ($this->mods as $id => $mod) {
            $course = $mod['course'];
            $mods[$course->id][] = $mod;
        }

        return $mods;
    }
}
