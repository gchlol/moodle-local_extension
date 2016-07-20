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
class request implements \cache_data_source {

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
            throw \coding_exception('No request id');
        }

        $requestid = $this->requestid;

        $this->request  = $DB->get_record('local_extension_request', array('id' => $requestid), '*', MUST_EXIST);
        $this->cms      = $DB->get_records('local_extension_cm', array('request' => $requestid), 'id ASC', 'cmid,course,data,handler,id,request,status,userid');
        $this->comments = $DB->get_records('local_extension_comment', array('request' => $requestid), 'timestamp ASC');

        $request = $this->request;

        $options = array(
            'requestid' => $request->id
        );

        list($handlers, $mods) = \local_extension\utility::get_activities($request->userid, $request->searchstart, $request->searchend, $options);
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
     * Fetches the attachments for this request.
     *
     * @return file_storage[]|stored_file[][]
     */
    public function fetch_attachments() {
        global $USER;

        $context = \context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_extension', 'attachments', $this->requestid);

        return array($fs, $files);
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

        // Invalidate the cache for this request. The content has changed.
        self::get_data_cache()->delete($this->requestid);
    }

    /**
     * Loads comments from the database into the request object.
     */
    public function load_comments() {
        global $DB;

        if (!empty($this->requestid)) {
            $this->comments = $DB->get_records('local_extension_comment', array('request' => $this->requestid), 'timestamp ASC');
        }
    }

    /**
     * Updates the cm with via posted data.
     *
     * @param moodleform $mform
     * @param stdClass $data
     */
    public function update_cm_status($user, $data) {

        foreach ($this->mods as $id => $mod) {
            $handler = $mod['handler'];
            $localcm = $mod['localcm'];
            $event   = $mod['event'];
            $course  = $mod['course'];

            $approve = 'approve' . $id;
            $deny = 'deny' . $id;
            if (!empty($data->$approve)) {
                $handler->set_state($localcm, $handler::STATUS_APPROVED);
                $status = $handler->get_status_name($handler::STATUS_APPROVED);
                $text = "$status extension for {$course->fullname}, {$event->name}";
                $this->add_comment($user, $text);
            }

            if (!empty($data->$deny)) {
                $handler->set_state($localcm, $handler::STATUS_DENIED);
                $status = $handler->get_status_name($handler::STATUS_DENIED);
                $text = "$status extension for {$course->fullname}, {$event->name}";
                $this->add_comment($user, $text);
            }

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

    /**
     * Returns the request cache.
     *
     * @return cache_application|cache_session|cache_store
     */
    public function get_data_cache() {
        return \cache::make('local_extension', 'requests');
    }

    /**
     * See cache_data_source::load_for_cache.
     *
     * {@inheritDoc}
     * @see cache_data_source::load_for_cache()
     * @return \local_extension\request
     */
    public function load_for_cache($requestid) {
        return self::from_id($requestid);
    }

    /**
     * See cache_data_source::load_many_for_cache.
     *
     * {@inheritDoc}
     * @see cache_data_source::load_many_for_cache()
     * @return \local_extension\request[]
     */
    public function load_many_for_cache(array $requestids) {
        $requests = array();

        foreach ($requestids as $requestid) {
            $requests[$requestid] = self::from_id($requestid);
        }

        return $requests;
    }

    /**
     * See cache_data_source::get_instance_for_cache.
     *
     * @param \cache_definition $definition
     * @return \local_extension\request
     */
    public static function get_instance_for_cache(\cache_definition $definition) {
        return new request();
    }

}
