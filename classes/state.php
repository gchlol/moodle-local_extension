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
 * Request state class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

use assign;
use coding_exception;
use context_module;
use html_writer;
use local_extension\request;
use moodle_url;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Request state class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state {
    /** @var state An instance of the state class. */
    private static $instance;

    /** @var int New request. */
    const STATE_NEW = 1;

    /** @var int Approved request. */
    const STATE_APPROVED = 2;

    /** @var int Denied request. */
    const STATE_DENIED = 3;

    /** @var int Reopened request. */
    const STATE_REOPENED = 4;

    /** @var int Cancelled request. */
    const STATE_CANCEL = 5;

    /** @var int Internal request. */
    const STATE_INTERNAL = 6;

    /** @var array An array of state ids */
    public $statearray = array();

    /**
     * Obtain an instance of this state machine.
     *
     * @return state
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new state();
        }

        return self::$instance;
    }

    /**
     * The state constructor.
     */
    protected function __construct() {
        $this->statearray = array(
            self::STATE_NEW => 'new',
            self::STATE_APPROVED => 'approve',
            self::STATE_DENIED => 'deny',
            self::STATE_REOPENED => 'reopen',
            self::STATE_CANCEL => 'cancel',
            self::STATE_INTERNAL => 'internal',
        );
    }

    /**
     * Protected clone.
     */
    protected function __clone() {
    }

    /**
     * Returns true if the specified state is in the window for length modification.
     *
     * @param int $stateid
     * @return bool
     */
    public static function can_modify_length_state($stateid) {
        switch ($stateid) {
            case self::STATE_NEW;
                return true;
            case self::STATE_REOPENED:
                return true;
            default:
                return false;
        }
    }

    /**
     * Returns a human readable state name.
     *
     * @param int $stateid State id.
     * @param boolean $raw If true, return the string only.
     * @throws \coding_exception
     * @return string the human-readable status name.
     */
    public function get_state_name($stateid, $raw = false) {
        switch ($stateid) {
            case self::STATE_NEW:
                $str = get_string('state_new',      'local_extension');
                if ($raw) {
                    return $str;
                }

                return html_writer::span($str, 'statusnew label');

            case self::STATE_DENIED:
                $str = get_string('state_denied',   'local_extension');
                if ($raw) {
                    return $str;
                }

                return html_writer::span($str, 'statusdenied label');

            case self::STATE_APPROVED:
                $str = get_string('state_approved', 'local_extension');
                if ($raw) {
                    return $str;
                }

                return html_writer::span($str, 'statusapproved label');

            case self::STATE_REOPENED:
                $str = get_string('state_reopened', 'local_extension');
                if ($raw) {
                    return $str;
                }

                return html_writer::span($str, 'statusreopened label');

            case self::STATE_CANCEL:
                $str = get_string('state_cancel',   'local_extension');
                if ($raw) {
                    return $str;
                }

                return html_writer::span($str, 'statuscancel label');

            case self::STATE_INTERNAL:
                $str = get_string('state_internal',   'local_extension');
                if ($raw) {
                    return $str;
                }

                return html_writer::span($str, 'statusinternal label');

            default:
                throw new coding_exception('Unknown cm state.');
        }
    }

    /**
     * Returns a string based on the state result.
     *
     * @param int $stateid State id.
     * @return string
     * @throws \coding_exception
     */
    public function get_state_result($stateid) {
        switch ($stateid) {
            case self::STATE_NEW:
            case self::STATE_REOPENED:
                return get_string('state_result_pending',   'local_extension');
            case self::STATE_DENIED:
                return get_string('state_result_denied',    'local_extension');
            case self::STATE_APPROVED:
                return get_string('state_result_approved',  'local_extension');
            case self::STATE_CANCEL:
                return get_string('state_result_cancelled', 'local_extension');
            default:
                throw new coding_exception('Unknown cm state.');
        }
    }

    /**
     * Returns true if the state is open/pending for a given $stateid.
     *
     * @param int $stateid
     * @return bool
     */
    public function is_open_state($stateid) {

        switch ($stateid) {
            case self::STATE_NEW:
            case self::STATE_REOPENED:
                return true;

            case self::STATE_DENIED:
            case self::STATE_APPROVED:
            case self::STATE_CANCEL:
                return false;

            default:
                return false;
        }

    }

    private function get_state_history($requestid, $localcmid) {
        global $DB;

        $sql = "SELECT id,
                       localcmid,
                       requestid,
                       timestamp,
                       state,
                       userid,
                       extlength
                  FROM {local_extension_hist_state}
                 WHERE requestid = :requestid
                   AND localcmid = :localcmid
              ORDER BY timestamp ASC";

        $params = [
            'requestid' => $requestid,
            'localcmid' => $localcmid,
        ];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * @param mod_data $mod
     * @param MoodleQuickForm $mform
     */
    public function render_state_definition($mod, $mform) {
        $localcm = $mod->localcm;

        // List of state changes ordered by ascending timestamp.
        $history = $this->get_state_history($localcm->requestid, $localcm->cmid);

        $this->render_override_state($mod, $mform, $history);
        $this->render_current_state($mod, $mform, $history);
        $this->render_pending_state($mod, $mform, $history);
        $this->render_state_history($mod, $mform, $history);
    }

    /**
     * @param mod_data $mod
     * @param MoodleQuickForm $mform
     * @param array $history
     */
    public function render_override_state($mod, $mform, $history) {
        if (!is_siteadmin()) {
            return false;
        }

        $handler = $mod->handler;
        $lateststate = null;

        // This is list of states sorted by timestamp.
        foreach ($history as $item) {
            if ($item->state == self::STATE_APPROVED) {
                $lateststate = $item;
            }
        }

        // There has been no state::STATE_APPROVED in the state change history.
        // We will not render any 'Current' state as it could be a manual extension granted.
        if ($lateststate === null) {
            return false;
        }

        $extdate = $handler->get_current_extension($mod);

        // No extension was found.
        if ($extdate === false) {
            return false;
        }

        // Obtain the due date of the most recent extension.
        $latestextensionlength = $lateststate->extlength + $mod->event->timestart;

        // If the most recent approved extension does not match the override, print the most recent.
        if ($extdate != $latestextensionlength) {
            $html = html_writer::div('Internal Extension');

            // The original assignment submission date.
            $duedate = $mod->event->timestart;

            $obj = new stdClass();
            $obj->date = userdate($extdate);
            $obj->length = utility::calculate_length($extdate - $duedate);
            $statusstring = get_string('status_status_summary_with_length', 'local_extension', $obj);

            $statusbadge = self::get_state_name(self::STATE_INTERNAL);
            $left = html_writer::div($statusbadge, 'statusbadge');
            $right = html_writer::div($statusstring);

            $html .= html_writer::tag('p', $left . $right);

            $mform->addElement('html', $html);

            return true;
        }


    }

    /**

    /**
     * @param mod_data $mod
     * @param MoodleQuickForm $mform
     * @param array $history
     */
    public function render_current_state($mod, $mform, $history) {
        $handler = $mod->handler;
        $lateststate = null;

        // This is list of states sorted by timestamp.
        foreach ($history as $item) {
            if ($item->state == self::STATE_APPROVED) {
                $lateststate = $item;
            }
        }

        // There has been no state::STATE_APPROVED in the state change history.
        // We will not render any 'Current' state as it could be a manual extension granted.
        if ($lateststate === null) {
            return false;
        }

        $extdate = $handler->get_current_extension($mod);

        // No extension was found.
        if ($extdate === false) {
            return false;
        }

        // Obtain the due date of the most recent extension.
        $latestextensionlength = $lateststate->extlength + $mod->event->timestart;

        // If the most recent approved extension does not match the override, print the most recent.
        if ($extdate != $latestextensionlength) {
            $extdate = $latestextensionlength;
        }

        $html = html_writer::div('Current Extension');

        // The original assignment submission date.
        $duedate = $mod->event->timestart;

        $obj = new stdClass();
        $obj->date = userdate($extdate);
        $obj->length = utility::calculate_length($extdate - $duedate);
        $statusstring = get_string('status_status_summary_with_length', 'local_extension', $obj);

        $statusbadge = self::get_state_name(self::STATE_APPROVED);
        $left = html_writer::div($statusbadge, 'statusbadge');
        $right = html_writer::div($statusstring);

        $html .= html_writer::tag('p', $left . $right);

        $mform->addElement('html', $html);

        return true;
    }

    /**
     * @param mod_data $mod
     * @param MoodleQuickForm $mform
     * @param array $history
     */
    public function render_pending_state($mod, $mform, $history) {

        $currentstate = $mod->localcm->get_stateid();
        if (self::instance()->is_open_state($currentstate)) {
            $html = html_writer::div('Pending Extension');

            $html .= html_writer::start_div();

            // The date of the current extension.
            $extdate = $mod->localcm->cm->data;

            // The original assignment submission date.
            $duedate = $mod->event->timestart;

            $obj = new stdClass();
            $obj->date = userdate($extdate);
            $obj->length = utility::calculate_length($extdate - $duedate);
            $statusstring = get_string('status_status_summary_with_length', 'local_extension', $obj);

            $statusbadge = self::get_state_name($currentstate);
            $left = html_writer::div($statusbadge, 'statusbadge');
            $right = html_writer::div($statusstring);

            $html .= html_writer::tag('p', $left . $right);

            $html .= html_writer::end_div();
            $mform->addElement('html', $html);
            return true;
        }

        return false;
    }

    /**
     * @param mod_data $mod
     * @param MoodleQuickForm $mform
     * @param array $history
     */
    public function render_state_history($mod, $mform, $history) {
        global $USER;

        $course = $mod->course;
        $context = \context_course::instance($course->id, MUST_EXIST);
        $forcestatus = has_capability('local/extension:modifyrequeststatus', $context);

        $approve = (rule::RULE_ACTION_APPROVE | rule::RULE_ACTION_FORCEAPPROVE);
        $access = rule::get_access($mod, $USER->id);

        // Admins and users with the capability will have the ability to view extra details with the state history.
        $adminrights = $forcestatus | ($access & $approve);

        $html = html_writer::div('Extension History');
        $mform->addElement('html', $html);

        $html = html_writer::start_div();

        // The initial start date will be the first comment in the local_extension_comment table for the requestid.
        foreach ($history as $state) {
            $statusbadge = self::get_state_name($state->state);

            $event = $mod->event;
            $date = $event->timestart + $state->extlength;

            $obj = new stdClass();
            $obj->status = $statusbadge;
            $obj->date = userdate($date);
            $obj->length = utility::calculate_length($state->extlength);

            // During the database upgrade to 2017062200 some state changes may not have any historic length.
            if (empty($obj->length)) {
                $moodlestring = 'status_status_summary_without_length';
            } else {
                $moodlestring = 'status_status_summary_with_length';
            }

            $left = html_writer::div($statusbadge, 'statusbadge');

            $rightstring = get_string($moodlestring, 'local_extension', $obj);

            if ($adminrights) {
                $extra = new stdClass();
                $user = \core_user::get_user($state->userid);
                $extra->date = userdate($state->timestamp);
                $extra->user = fullname($user);
                $rightstring .= ' ' . get_string('status_status_summary_extra_details', 'local_extension', $extra);
            }

            $right = html_writer::div($rightstring);

            $html .= html_writer::tag('p', $left . $right);
        }

        $html .= html_writer::end_div();

        $mform->addElement('html', $html);
    }

    /**
     * Renders the approve buttons for a standard user that can approve or deny an extension.
     *
     * @param MoodleQuickForm $mform
     * @param int $state
     * @param cm $cm
     */
    public function render_approve_buttons(&$mform, $state, $cm) {
        $statebuttons = [];
        $extrabuttons = [];

        $id = $cm->cmid;

        $approvestr = get_string('state_button_approve', 'local_extension');
        $denystr = get_string('state_button_deny', 'local_extension');
        $additionalstr = get_string('state_button_additional_request', 'local_extension');
        $modifystr = get_string('state_button_modfiy_length', 'local_extension');

        $deny = $this->statearray[self::STATE_DENIED];
        $approve = $this->statearray[self::STATE_APPROVED];

        switch ($state) {
            case self::STATE_NEW:
                $statebuttons[] = $mform->createElement('submit', $approve . $id, $approvestr);
                $statebuttons[] = $mform->createElement('submit', $deny . $id, $denystr);
                break;
            case self::STATE_REOPENED:
                $statebuttons[] = $mform->createElement('submit', $approve . $id, $approvestr);
                $statebuttons[] = $mform->createElement('submit', $deny . $id, $denystr);
                break;
            default:
                break;
        }

        if (!empty($statebuttons)) {
            $mform->addGroup($statebuttons, 'statusmodgroup' . $id, '', ' ', false);
        }

        $extrabuttons[] = $mform->createElement('submit', 'modifyextension' . $id, $modifystr);
        $extrabuttons[] = $mform->createElement('submit', 'additionalextention' . $id, $additionalstr);
        $mform->addGroup($extrabuttons, 'extramodgroup' . $id, '', ' ', false);
    }

    /**
     * Renders the approve buttons for a the owner of the request/cms.
     *
     * @param MoodleQuickForm $mform
     * @param int $state
     * @param cm $cm
     */
    public function render_owner_buttons(&$mform, $state, $cm) {
        $statebuttons = [];
        $extrabuttons = [];

        $id = $cm->cmid;

        $cancelstr = get_string('state_button_cancel', 'local_extension');
        $reopenstr = get_string('state_button_reopen', 'local_extension');
        $additionalstr = get_string('state_button_additional_request', 'local_extension');

        $cancel = $this->statearray[self::STATE_CANCEL];
        $reopen = $this->statearray[self::STATE_REOPENED];

        switch ($state) {
            case self::STATE_NEW:
                $statebuttons[] = $mform->createElement('submit', $cancel . $id, $cancelstr);
                break;
            case self::STATE_REOPENED:
                $statebuttons[] = $mform->createElement('submit', $cancel . $id, $cancelstr);
                break;
            case self::STATE_CANCEL:
                $statebuttons[] = $mform->createElement('submit', $reopen . $id, $reopenstr);
                break;
            default:
                break;
        }

        if (!empty($statebuttons)) {
            $mform->addGroup($statebuttons, 'statusmodgroup' . $id, '', ' ', false);
        }

        $extrabuttons[] = $mform->createElement('submit', 'additionalextention' . $id, $additionalstr);
        $mform->addGroup($extrabuttons, 'extramodgroup' . $id, '', ' ', false);
    }

    /**
     * Renders the approve buttons for an admin user that can approve, deny or cancel an extension.
     *
     * @param MoodleQuickForm $mform
     * @param int $state
     * @param cm $cm
     */
    public function render_force_buttons(&$mform, $state, $cm) {
        $statebuttons = [];
        $extrabuttons = [];

        $id = $cm->cmid;

        $approvestr = get_string('state_button_approve', 'local_extension');
        $cancelstr = get_string('state_button_cancel', 'local_extension');
        $denystr = get_string('state_button_deny', 'local_extension');
        $reopenstr = get_string('state_button_reopen', 'local_extension');
        $additionalstr = get_string('state_button_additional_request', 'local_extension');
        $modifystr = get_string('state_button_modfiy_length', 'local_extension');

        $deny = $this->statearray[self::STATE_DENIED];
        $approve = $this->statearray[self::STATE_APPROVED];
        $cancel = $this->statearray[self::STATE_CANCEL];
        $reopen = $this->statearray[self::STATE_REOPENED];

        switch ($state) {
            case self::STATE_NEW:
                $statebuttons[] = $mform->createElement('submit', $approve . $id, $approvestr);
                $statebuttons[] = $mform->createElement('submit', $deny . $id, $denystr);
                $statebuttons[] = $mform->createElement('submit', $cancel . $id, $cancelstr);
                break;
            case self::STATE_REOPENED:
                $statebuttons[] = $mform->createElement('submit', $approve . $id, $approvestr);
                $statebuttons[] = $mform->createElement('submit', $deny . $id, $denystr);
                $statebuttons[] = $mform->createElement('submit', $cancel . $id, $cancelstr);
                break;
            case self::STATE_DENIED:
                $statebuttons[] = $mform->createElement('submit', $approve . $id, $approvestr);
                $statebuttons[] = $mform->createElement('submit', $reopen . $id, $reopenstr);
                $statebuttons[] = $mform->createElement('submit', $cancel . $id, $cancelstr);
                break;
            case self::STATE_CANCEL:
                $statebuttons[] = $mform->createElement('submit', $reopen . $id, $reopenstr);
                break;
            case self::STATE_APPROVED:
                $statebuttons[] = $mform->createElement('submit', $deny . $id, $denystr);
                $statebuttons[] = $mform->createElement('submit', $cancel . $id, $cancelstr);
                break;
            default:
                break;
        }

        if (!empty($statebuttons)) {
            $mform->addGroup($statebuttons, 'statusmodgroup' . $id, '', ' ', false);
        }

        $extrabuttons[] = $mform->createElement('submit', 'modifyextension' . $id, $modifystr);
        $extrabuttons[] = $mform->createElement('submit', 'additionalextention' . $id, $additionalstr);
        $mform->addGroup($extrabuttons, 'extramodgroup' . $id, '', ' ', false);
    }

    /**
     * If the $data object contains a state change reference, redirect the page to an intermediate acknowledgement.
     *
     * @param stdClass $data
     * @param request $request
     */
    public function has_submitted_state_change($data, $request) {
        // Iterate over the request mods to obtain the cmid.
        foreach ($request->mods as $id => $mod) {
            // Iterate over the possible states.
            foreach ($this->statearray as $state => $name) {
                // A state could be approve19.
                $item = $name . $id;

                // We found it! The state has changed.
                if (!empty($data->$item)) {
                    $cm = $mod->cm;
                    $params = array(
                        'id' => $request->requestid,
                        'course' => $cm->course,
                        'cmid' => $cm->id,
                        's' => $state,
                    );

                    redirect(new moodle_url('/local/extension/state.php', $params));
                }
            }
        }
    }

    /**
     * If the $data object contains an additional extension reference, redirect the page to allow the student to
     * extend the request length.
     *
     * @param stdClass $data
     * @param request $request
     */
    public function has_submititted_additional_request($data, $request) {
        // Iterate over the request mods to obtain the cmid.
        foreach ($request->mods as $id => $mod) {
            $extend = 'additionalextention' . $id;
            $modify = 'modifyextension' . $id;

            $params = [
                'id'       => $request->requestid,
                'courseid' => $mod->cm->course,
                'cmid'     => $mod->cm->id,
            ];

            if (!empty($data->$extend)) {
                redirect(new moodle_url('/local/extension/additional_request.php', $params));
            }

            if (!empty($data->$modify)) {
                redirect(new moodle_url('/local/extension/modify.php', $params));
            }
        }
    }

    /**
     * Updates the cm state with posted data.
     *
     * @param request $request
     * @param int $user
     * @param stdClass $data
     * @return object|bool
     */
    public function update_cm_state($request, $user, $data) {

        $mod = $request->mods[$data->cmid];

        $handler = $mod->handler;

        $localcm = $mod->localcm;
        $event   = $mod->event;
        $course  = $mod->course;

        // The form data passed through contains the state.
        $state = $data->s;

        // The extension has been approved. Lets hook into the handler and extend the items length.
        if ($state == self::STATE_APPROVED) {
            $handler->submit_extension($event->instance,
                                       $request->request->userid,
                                       $localcm->cm->data);

        } else if ($state == self::STATE_CANCEL ||
                   $state == self::STATE_DENIED) {
            $handler->cancel_extension($event->instance,
                                       $request->request->userid);
        }

        $ret = $localcm->set_state($state);
        if (empty($ret)) {
            return false;
        }

        $status = $this->get_state_name($localcm->cm->state);

        // After writing the history it will return the ID of the new row.
        $history = $localcm->write_history($mod, $state, $user->id);

        $log = new stdClass();
        $log->status = $status;
        $log->course = $course->fullname;
        $log->event = $event->name;

        $history->message = get_string('request_state_history_log', 'local_extension', $log);

        // When cancelling or denying a request, it may be that an existing request is present.
        if ($state == self::STATE_CANCEL ||
            $state == self::STATE_DENIED) {

            // Obtain the latest approved state.
            $lastapproved = $this->get_last_approved_extension($mod);

            // If an existing approved history item exists.
            if ($lastapproved) {
                $localcm->set_state(self::STATE_APPROVED);
                $localcm->cm->length = $lastapproved->extlength;
                $localcm->cm->data = $event->timestart + $lastapproved->extlength;
                $localcm->update_data();
            }
        }

        // Update the lastmod.
        $request->update_lastmod($user->id);

        // You can only edit one state at a time, returning here is ok!
        return $history;
    }

    /**
     * Searches the history of state changes and finds the most recent approved state.
     *
     * @param $mod
     * @return array|bool
     */
    private function get_last_approved_extension($mod) {
        global $DB;

        $lcm = $mod->localcm;

        $sql = "SELECT *
                  FROM {local_extension_hist_state} hs
                 WHERE hs.requestid = :requestid
                   AND hs.state = :state
                   AND hs.localcmid = :localcmid
              ORDER BY hs.id ASC";
        $params = [
            'requestid' => $lcm->cm->request,
            'state' => self::STATE_APPROVED,
            'userid' => $lcm->cm->userid,
            'localcmid' => $lcm->cm->cmid,
        ];

        $approved = $DB->get_records_sql($sql, $params);

        if ($approved) {
            return array_pop($approved);
        }

        return false;
    }

    /**
     * When a cm has been approved and the length has been updated then we must call the handler and update the extension.
     *
     * @param request $request
     * @param int $user The user that has called this operation.
     * @param stdClass $data
     * @return object|bool
     */
    public function extend_cm_length($request, $user, $data) {

        $mod = $request->mods[$data->cmid];
        $handler = $mod->handler;

        $localcm = $mod->localcm;
        $event   = $mod->event;
        $course  = $mod->course;

        $handler->cancel_extension($event->instance, $request->request->userid);
        $handler->submit_extension($event->instance, $request->request->userid, $localcm->cm->data);

        $state = $localcm->get_stateid();
        $status = $this->get_state_name($state);

        // After writing the history it will return the ID of the new row.
        $history = $localcm->write_history($mod, $state, $user->id);

        $log = new stdClass();
        $log->status = $status;
        $log->course = $course->fullname;
        $log->event = $event->name;

        $history->message = get_string('request_state_history_log', 'local_extension', $log);

        // Update the lastmod.
        $request->update_lastmod($user->id);

        // You can only edit one state at a time, returning here is ok!
        return $history;
    }

    /**
     * Checks the current state and returns true if the requested state is possible.
     *
     * @param int $currentstate
     * @param int $requestedstate
     * @param bool $approved
     * @return bool
     */
    public function state_is_possible($currentstate, $requestedstate, $approved) {

        $states = array();

        switch ($currentstate) {
            case self::STATE_NEW:
                $states = array(self::STATE_CANCEL);

                if ($approved) {
                    $states[] = self::STATE_APPROVED;
                    $states[] = self::STATE_DENIED;
                }
                break;

            case self::STATE_REOPENED:
                $states = array(self::STATE_CANCEL);

                if ($approved) {
                    $states[] = self::STATE_DENIED;
                    $states[] = self::STATE_APPROVED;
                }
                break;

            case self::STATE_DENIED:
                if ($approved) {
                    $states = array(
                        self::STATE_APPROVED,
                        self::STATE_REOPENED,
                        self::STATE_CANCEL,
                    );
                }
                break;

            case self::STATE_CANCEL:
                $states = array(self::STATE_REOPENED);
                break;

            case self::STATE_APPROVED:
                if ($approved) {
                    $states = array(
                        self::STATE_CANCEL,
                        self::STATE_DENIED,
                    );
                }
                break;

            default:
                break;
        }

        if (in_array($requestedstate, $states)) {
            return true;
        }

        return false;
    }

}
