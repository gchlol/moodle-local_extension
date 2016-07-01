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

    /** @var integer The request state. */
    private $state = 0;

    /** @var integer New request. */
    const STATUS_NEW      = 0;

    /** @var integer Denied request. */
    const STATUS_DENIED   = 1;

    /** @var integer Approved request. */
    const STATUS_APPROVED = 2;

    /** @var integer Reopened request. */
    const STATUS_REOPENED = 3;

    /** @var integer Cancelled request. */
    const STATUS_CANCEL   = 4;

    /**
     * Request object constructor.
     * @param integer $reqid An optional variable to identify the request.
     */
    public function __construct($requestid = null) {
        $this->requestid = $requestid;
    }

    /**
     * Loads data into the object
     */
    public function load() {
        global $DB;

        if (empty($this->requestid)) {
            throw coding_exception('No request id');
        }

        $reqid = $this->requestid;

        $this->request  = $DB->get_record('local_extension_request', array('id' => $reqid));
        $this->cms      = $DB->get_records('local_extension_cm', array('request' => $reqid));
        $this->comments = $DB->get_records('local_extension_comment', array('request' => $reqid));

        $userids     = array();
        $userrecords = array();

        // TODO need to sort cms by date and comments by date.
        // Obtain a unique list of userids that have been commenting.
        foreach ($this->comments as $comment) {
            $userids[$comment->userid] = $comment->userid;
        }

        // Fetch the users.
        // TODO change this to single call using get_in_or_equal .
        foreach ($userids as $uid) {
            $userrecords[$uid] = $DB->get_record('user', array('id' => $uid), \user_picture::fields());
        }

        $this->users = $userrecords;

        $this->files = $this->fetch_attachments($reqid);
    }

    /**
     * Obtain request data for the renderer.
     *
     * @param integer $reqid An id for a request.
     * @return request $req A request data object.
     */
    public static function from_id($reqid) {
        $request = new request($reqid);
        $request->load();
        return $request;
    }

    /**
     * Fetch the list of attached files for the request id.
     *
     * @param integer $reqid An id for a request.
     * @return array An array of file objects.
     */
    public function fetch_attachments($reqid) {
        global $USER;

        $context = \context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_extension', 'attachments', $reqid);

        return $files;
    }

    /**
     * Adds a comment to the request.
     *
     * @param unknown $from
     * @param unknown $comment
     * @param unknown $format
     */
    public function add_comment($from, $comment, $format) {

    }

    /**
     * Returns the state of this request.
     *
     * @return integer $state The state.
     */
    public function get_state() {
        return $this->state;
    }

    public function get_state_next() {
        switch ($this->state) {
            case self::STATUS_NEW:
                return [];
            case self::STATUS_DENIED:
                return [];
            case self::STATUS_APPROVED:
                return [];
            case self::STATUS_REOPENED:
                return [];
            case self::STATUS_REOPENED:
                return [];
            default:
                self::STATUS_DENIED;
        }
    }

    /**
     * Returns a human readable state name.
     *
     * @param string $state one of the state constants like STATUS_NEW.
     * @throws coding_exception
     * @return string the human-readable state name.
     */
    public function get_state_name($state) {
        switch ($state) {
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
}