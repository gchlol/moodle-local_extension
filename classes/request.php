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

    /** @var \stdClass The local_extension_request database object */
    public $request = array();

    /** @var cm[] cm */
    public $cms = array();

    /** @var array An array of comment objects from the request id */
    public $comments = array();

    /** @var array An array of state change objects from the request id */
    public $statechanges = array();

    /** @var array An array of file attachment objects from the request id */
    public $attachments = array();

    /** @var array An array of user objects with the available fields user_picture::fields  */
    public $users = array();

    /** @var array An array of attached files that exist for this request id */
    public $files = array();

    /** @var array An array of mods that are used */
    public $mods = array();

    /** @var array A list of subscribed user ids */
    public $subscribedids = array();

    /**
     * Request object constructor.
     * @param integer $requestid An optional variable to identify the request.
     */
    public function __construct($requestid = null) {
        $this->requestid = $requestid;
    }

    /**
     * Loads data into the object
     * @throws \coding_exception
     */
    public function load() {
        global $DB;

        if (empty($this->requestid)) {
            throw \coding_exception('No request id');
        }

        $this->request  = $DB->get_record('local_extension_request', array('id' => $this->requestid), '*', MUST_EXIST);
        $this->comments = $DB->get_records('local_extension_comment', array('request' => $this->requestid), 'timestamp ASC');

        $requestid = $this->requestid;
        $request = $this->request;

        $options = array(
            'requestid' => $requestid
        );

        list($handlers, $mods) = \local_extension\utility::get_activities($request->userid, $request->searchstart, $request->searchend, $options);
        $this->mods = $mods;

        foreach ($mods as $id => $mod) {
            $cm = $mod['localcm'];
            $this->cms[$cm->cmid] = $cm;
        }

        // By deafult add the user that has initiated the request to the list of ids associated with this request.
        $userids = array($request->userid => $request->userid);

        // Obtain a unique list of userids that have been commenting.
        foreach ($this->comments as $comment) {
            $userids[$comment->userid] = $comment->userid;
        }

        // Add to the $userids, a list of users that are subscribed to this request.
        $fieldset = $DB->get_fieldset_select('local_extension_subscription', 'userid', 'requestid = :requestid', array('requestid' => $this->requestid));
        $this->subscribedids[] = $request->userid;

        // Remove duplicate subscriber ids to prevent spamming them.
        $this->subscribedids = array_merge($this->subscribedids, array_unique($fieldset));

        foreach ($this->subscribedids as $key => $userid) {
            $userids[$userid] = $userid;
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
     * Obtain a request class with the given id.
     *
     * @param integer $requestid An id for a request.
     * @return \local_extension\request $request A request data object.
     */
    public static function from_id($requestid) {
        $request = new request($requestid);
        $request->load();
        return $request;
    }

    /**
     * Fetches the attachments for this request.
     *
     * @return array
     */
    public function fetch_attachments() {
        $request = $this->request;

        // Obtain the context for the user that has submitted a request.
        $usercontext = \context_user::instance($request->userid);

        $fs = get_file_storage();

        $files = $fs->get_area_files($usercontext->id, 'local_extension', 'attachments', $request->id);

        return array($fs, $files);
    }

    /**
     * Adds a comment to the request
     *
     * @param \stdClass $from The user that has commented.
     * @param string $comment The comment itself.
     * @return object|string
     */
    public function add_comment($from, $comment) {
        global $DB;

        $comment = (object) array(
            'request'       => $this->requestid,
            'userid'        => $from->id,
            'timestamp'     => \time(),
            'message'       => $comment,
        );
        $DB->insert_record('local_extension_comment', $comment);

        return $comment;
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
     * Increments the message id value to assist with threading email notification messages.
     */
    public function increment_messageid() {
        global $DB;

        $this->request->messageid++;

        $DB->update_record('local_extension_request', $this->request);

        // The request has changed, lets invalidate the cache.
        $this->invalidate_request();
    }

    /**
     * Sort all comments, state changes and attachments based on timestamp.
     *
     * @param array $history
     */
    public function sort_history(&$history) {
        // Sort all comments, state changes and attachments based on timestamp.
        usort($history, function($a, $b) {

            // If the timestamps are the same, always return the status update/file attachment first, comments second.
            if ($a->timestamp == $b->timestamp) {
                if (property_exists($a, 'state')) {
                    return 1;
                } else if (property_exists($b, 'state')) {
                    return 1;
                }

                if (property_exists($a, 'filehash')) {
                    return 1;
                } else if (property_exists($b, 'filehash')) {
                    return 1;
                }
            }

            return $a->timestamp - $b->timestamp;
        });
    }

    /**
     * Returns the attachment and statechange history to be used with interleaving the comment stream when viewing status.php
     *
     * @return array $history
     */
    public function get_history() {
        $history = array();

        $history += $this->comment_history();
        $history += $this->state_history();
        $history += $this->attachment_history();

        $this->sort_history($history);

        return $history;
    }

    /**
     * Returns the comment history to be used with interleaving the comment stream when viewing status.php
     */
    private function comment_history() {
        return $this->comments;
    }

    /**
     * Returns the state change history to be used with interleaving the comment stream when viewing status.php
     *
     * @return array $history
     */
    private function state_history() {
        global $DB;

        $history = array();

        // Selecting the state changes from the history for this request.
        $sql = "SELECT id,
                       localcmid,
                       requestid,
                       timestamp,
                       state,
                       userid
                  FROM {local_extension_his_state}
                 WHERE requestid = :requestid";

        $records = $DB->get_records_sql($sql, array('requestid' => $this->requestid));

        foreach ($records as $record) {
            $mod = $this->mods[$record->localcmid];

            /* @var \local_extension\cm $localcm */
            $localcm = $mod['localcm'];
            $event   = $mod['event'];
            $course  = $mod['course'];

            $status = $localcm->get_state_name($record->state);
            $log = "$status extension for {$course->fullname}, {$event->name}";

            // Add class property 'message' to interleave with the comment stream.
            $record->message = $log;

            $history[$record->id] = $record;
        }

        return $history;
    }

    /**
     * Returns the attachment change history to be used with interleaving the comment stream when viewing status.php
     *
     * @return array $history
     */
    private function attachment_history() {
        global $DB;

        $history = array();

        $fs = get_file_storage();

        // Selecting the attachment history for this request.
        $sql = "SELECT id,
                       requestid,
                       timestamp,
                       filehash,
                       userid
                  FROM {local_extension_history_file}
                 WHERE requestid = :requestid";

        $records = $DB->get_records_sql($sql, array('requestid' => $this->requestid));

        foreach ($records as $record) {
            $file = $fs->get_file_by_hash($record->filehash);

            if (empty($file)) {
                continue;
            }

            $fileurl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            $filelink = \html_writer::link($fileurl, $file->get_filename());

            // Add class property 'message' to interleave with the comment stream.
            $record->message = get_string('status_file_attachment', 'local_extension', $filelink);

            $history[] = $record;
        }

        return $history;
    }

    /**
     * Each cm may have a different set of rules that will need to be processed.
     */
    public function process_triggers() {

        $notifydata = array();
        $templatedata = array();

        // There can only be one request per course module.
        foreach ($this->mods as $mod) {
            $handler = $mod['handler'];

            // Obtains all rules based on the handlers datatype. eg. assign/quiz.
            $rules = $handler->get_triggers();

            // Creates a tree structure with the rules to process.
            $ordered = \local_extension\utility::rule_tree($rules);

            foreach ($ordered as $rule) {
                $return = $this->process_recursive($mod, $rule);
                $notifydata = array_merge($notifydata, $return);
            }

        }

        // We have processed the triggers, lets send some emails!
        if (!empty($notifydata)) {

            /** @var rule[] $rules */
            $rules = array();

            // 1. Generate the template content for each mod item
            foreach ($notifydata as $data) {
                /** @var rule $rule */
                $rule = $data->rule;

                // For the rule is that triggered, here is a list of cm templates that are generated.
                $templatedata[$rule->id][] = $rule->process_templates($this, $data->mod);
                $rules[$rule->id] = $rule;
            }

            // 2. Join the template subjects and content together in one message
            foreach ($templatedata as $ruleid => $templatecms) {
                // If there are multiple cms in a request we need to concatenate them into the one message.

                $templates = new \stdClass();

                foreach ($templatecms as $template) {

                    $types = array(
                        'template_user'   => 'user_content',
                        'template_notify' => 'role_content',
                    );

                    foreach ($types as $templatekey => $attribute) {

                        // Checking if the form editor 'text' content has not been found.
                        if (!array_key_exists('text', $template[$templatekey])) {
                            continue;
                        }

                        // Setting the attributes to be empty if the template data is not found.
                        // FIX for deleting moodle editor data, it leaves a <br> in the text after ctrl+a text deletion :(
                        if (empty(strip_tags($template[$templatekey]['text']))) {
                            $templates->$attribute = null;
                            continue;
                        }

                        $content = $template[$templatekey]['text'];

                        // Checks to see if the return attribute *_content is set, if is, then it appends a <hr> and the next template item.
                        if (!empty($templates->$attribute)) {
                            $templates->$attribute .= "<hr>" . $content;
                        } else {
                            $templates->$attribute = $content;
                        }

                    }

                }

                // 3. Notify the roles / user for each rule returned.
                $rules[$ruleid]->send_notifications($this, $mod, $templates);
            }

            // Notifications have been sent out. Increment the messageid to thread messages.
            $this->increment_messageid();
        }

        // Invalidate the cache for this request, there may be new users subscribed.
        $this->invalidate_request();
    }

    /**
     * Processes the rule and request recursively.
     *
     * @param array $mod
     * @param \local_extension\rule $rule
     * @param array $data
     * @return array
     */
    private function process_recursive($mod, $rule, &$data = null) {

        // Initialise the data object.
        if (empty($data)) {
            $data = array();
        }

        // Processing a rule.
        $notify = $rule->process($this, $mod);

        // If true, then we will create a notification data entry that will be returned.
        if ($notify === true) {
            $item = new \stdClass();

            $item->rule = $rule;
            $item->role = $rule->role;
            $item->mod = $mod;
            $item->cmid = $mod['localcm']->cm->cmid;

            $data[] = $item;
        }

        // If the rule has children, then we will process them.
        if (!empty($rule->children)) {

            foreach ($rule->children as $child) {
                $data = $this->process_recursive($mod, $child, $data);
            }

        }

        return $data;
    }

    /**
     * Iterates over every localcm and checks the status. If they are all cancelled then this will return false.
     *
     * @return boolean
     */
    public function check_active() {
        foreach ($this->cms as $cm) {
            $state = $cm->get_stateid();

            if ($state != \local_extension\cm::STATE_CANCEL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Updates the cm state with via posted data.
     *
     * @param integer $user
     * @param \stdClass $data
     * @return object
     */
    public function update_cm_state($user, $data) {

        foreach ($this->mods as $id => $mod) {
            /* @var \local_extension\base_request $handler */
            $handler = $mod['handler'];

            /* @var \local_extension\cm $localcm */
            $localcm = $mod['localcm'];
            $event   = $mod['event'];
            $course  = $mod['course'];

            $statearray = array(
                'approve' => \local_extension\cm::STATE_APPROVED,
                'deny'    => \local_extension\cm::STATE_DENIED,
                'cancel'  => \local_extension\cm::STATE_CANCEL,
                'reopen'  => \local_extension\cm::STATE_REOPENED,
            );

            foreach ($statearray as $name => $state) {
                $item = $name . $id;

                if (!empty($data->$item)) {

                    // The extension has been approved. Lets hook into the handler and extend the items length.
                    if ($name == 'approve') {
                        $handler->submit_extension($event->id, $this->request->userid, $localcm->cm->data);
                    }

                    $localcm->set_state($state);

                    $status = $localcm->get_state_name();

                    $history = (object) $localcm->write_history($mod, $state, $user->id);

                    $history->message = "$status extension for {$course->fullname}, {$event->name}";

                    // You can only edit one state at a time, returning here is ok!
                    return $history;
                }

            }

        }

    }

    /**
     * Adds a stored_file hash to the history for this request.
     *
     * @param \stored_file $file
     * @return null|object
     */
    public function add_attachment_history(\stored_file $file) {
        global $DB;

        if ($file->is_directory()) {
            return null;
        }

        $data = array(
            'requestid' => $this->requestid,
            'timestamp' => $file->get_timecreated(),
            'filehash'  => $file->get_pathnamehash(),
            'userid'    => $file->get_userid(),
        );

        $DB->insert_record('local_extension_history_file', $data);

        $fileurl = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        $filelink = \html_writer::link($fileurl, $file->get_filename());
        // Add class property 'message' to interleave with the comment stream.
        $history = (object) $data;
        $history->message = get_string('status_file_attachment', 'local_extension', $filelink);

        return $history;
    }

    /**
     * Each comment, state change and file attachment will fire off a notification to all subscribed users.
     *
     * @param array $history
     * @param int $postuserid The ID of the user that updated the request. This user will not receive an notification as they posted the content.
     */
    public function notify_subscribers($history, $postuserid) {
        global $PAGE;

        $this->sort_history($history);

        foreach ($this->subscribedids as $userid) {

            // Do not send a notification to the user that has posted the update.
            if ($postuserid == $userid) {
                continue;
            }

            $userto = \core_user::get_user($userid);

            $comments = '';
            foreach ($history as $item) {
                $comments .= $PAGE->get_renderer('local_extension')->render_single_comment($this, $item, true);
            }

            // The update is sent from who modified the history.
            // There will always be at least one history item.
            $userfrom = \core_user::get_user($history[0]->userid);

            $requestuser = \core_user::get_user($this->request->userid);
            $fullname = \fullname($requestuser, true);

            $data = new \stdClass();
            $data->requestid = $this->requestid;
            $data->fullname = $fullname;

            $subject = get_string('email_notification_subect', 'local_extension', $data);

            $statusurl = new \moodle_url("/local/extension/status.php", array('id' => $this->requestid));

            $obj = new \stdClass();
            $obj->content = $comments;
            $obj->id = $this->requestid;
            $obj->fullname = $fullname;
            $obj->statusurl = $statusurl->out(true);

            $content = get_string('notification_footer', 'local_extension', $obj);

            \local_extension\utility::send_trigger_email($this, $subject, $content, $userfrom, $userto);
        }

        // Increment the messageid to assist with inbox threading / message history.
        $this->increment_messageid();
    }

    /**
     * Returns the request cache.
     *
     * @return \cache_application|\cache_session|\cache_store
     */
    public function get_data_cache() {
        return \cache::make('local_extension', 'requests');
    }

    /**
     * See cache_data_source::load_for_cache.
     *
     * {@inheritDoc}
     * @see cache_data_source::load_for_cache()
     *
     * @param integer $requestid
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
     *
     * @param array $requestids
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

    /**
     * Invalidates the cache for this request.
     */
    public function invalidate_request() {
        $this->get_data_cache()->delete($this->requestid);
    }

}
