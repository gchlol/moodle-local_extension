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
    const STATE_NEW = 0;

    /** @var int Approved request. */
    const STATE_APPROVED = 1;

    /** @var int Denied request. */
    const STATE_DENIED = 2;

    /** @var int Reopened request. */
    const STATE_REOPENED = 3;

    /** @var int Cancelled request. */
    const STATE_CANCEL = 4;

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
            'approve' => self::STATE_APPROVED,
            'deny'    => self::STATE_DENIED,
            'cancel'  => self::STATE_CANCEL,
            'reopen'  => self::STATE_REOPENED,
        );
    }

    /**
     * Protected clone.
     */
    protected function __clone() {
    }

    /**
     * Returns a human readable state name.
     *
     * @param int $stateid State id.
     * @throws \coding_exception
     * @return string the human-readable status name.
     */
    public function get_state_name($stateid) {
        switch ($stateid) {
            case self::STATE_NEW:
                return \get_string('state_new',      'local_extension');
            case self::STATE_DENIED:
                return \get_string('state_denied',   'local_extension');
            case self::STATE_APPROVED:
                return \get_string('state_approved', 'local_extension');
            case self::STATE_REOPENED:
                return \get_string('state_reopened', 'local_extension');
            case self::STATE_CANCEL:
                return \get_string('state_cancel',   'local_extension');
            default:
                throw new \coding_exception('Unknown cm state.');
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
                return \get_string('state_result_pending',   'local_extension');
            case self::STATE_DENIED:
                return \get_string('state_result_denied',    'local_extension');
            case self::STATE_APPROVED:
                return \get_string('state_result_approved',  'local_extension');
            case self::STATE_CANCEL:
                return \get_string('state_result_cancelled', 'local_extension');
            default:
                throw new \coding_exception('Unknown cm state.');
        }
    }

    /**
     * Renders the approve buttons for a standard user that can approve or deny an extension.
     *
     * @param \MoodleQuickForm $mform
     * @param int $state
     * @param int $id
     */
    public function render_approve_buttons(&$mform, $state, $id) {
        $buttonarray = array();

        $approve = get_string('state_button_approve', 'local_extension');
        $cancel = get_string('state_button_cancel', 'local_extension');
        $deny = get_string('state_button_deny', 'local_extension');
        $reopen = get_string('state_button_reopen', 'local_extension');

        switch ($state) {
            case self::STATE_NEW:
                $buttonarray[] = $mform->createElement('submit', 'approve' . $id, $approve);
                $buttonarray[] = $mform->createElement('submit', 'deny' . $id, $deny);
                break;
            case self::STATE_REOPENED:
                $buttonarray[] = $mform->createElement('submit', 'approve' . $id, $approve);
                $buttonarray[] = $mform->createElement('submit', 'deny' . $id, $deny);
                break;
            default:
                break;
        }

        if (!empty($buttonarray)) {
            $mform->addGroup($buttonarray, 'statusmodgroup' . $id, '', ' ', false);
        }
    }

    /**
     * Renders the approve buttons for a the owner of the request/cms.
     *
     * @param \MoodleQuickForm $mform
     * @param int $state
     * @param int $id
     */
    public function render_owner_buttons(&$mform, $state, $id) {
        $buttonarray = array();

        $cancel = get_string('state_button_cancel', 'local_extension');
        $reopen = get_string('state_button_reopen', 'local_extension');

        switch ($state) {
            case self::STATE_NEW:
                $buttonarray[] = $mform->createElement('submit', 'cancel' . $id, $cancel);
                break;
            case self::STATE_REOPENED:
                $buttonarray[] = $mform->createElement('submit', 'cancel' . $id, $cancel);
                break;
            case self::STATE_CANCEL:
                $buttonarray[] = $mform->createElement('submit', 'reopen' . $id, $reopen);
                break;
            default:
                break;
        }

        if (!empty($buttonarray)) {
            $mform->addGroup($buttonarray, 'statusmodgroup' . $id, '', ' ', false);
        }
    }

    /**
     * Renders the approve buttons for an admin user that can approve, deny or cancel an extension.
     *
     * @param \MoodleQuickForm $mform
     * @param int $state
     * @param int $id
     */
    public function render_force_buttons(&$mform, $state, $id) {
        $buttonarray = array();

        $approve = get_string('state_button_approve', 'local_extension');
        $cancel = get_string('state_button_cancel', 'local_extension');
        $deny = get_string('state_button_deny', 'local_extension');
        $reopen = get_string('state_button_reopen', 'local_extension');

        switch ($state) {
            case self::STATE_NEW:
                $buttonarray[] = $mform->createElement('submit', 'approve' . $id, $approve);
                $buttonarray[] = $mform->createElement('submit', 'deny' . $id, $deny);
                $buttonarray[] = $mform->createElement('submit', 'cancel' . $id, $cancel);
                break;
            case self::STATE_REOPENED:
                $buttonarray[] = $mform->createElement('submit', 'approve' . $id, $approve);
                $buttonarray[] = $mform->createElement('submit', 'deny' . $id, $deny);
                $buttonarray[] = $mform->createElement('submit', 'cancel' . $id, $cancel);
                break;
            case self::STATE_DENIED:
                $buttonarray[] = $mform->createElement('submit', 'reopen' . $id, $reopen);
                $buttonarray[] = $mform->createElement('submit', 'cancel' . $id, $cancel);
                break;
            case self::STATE_CANCEL:
                $buttonarray[] = $mform->createElement('submit', 'reopen' . $id, $reopen);
                break;
            case self::STATE_APPROVED:
                $buttonarray[] = $mform->createElement('submit', 'deny' . $id, $deny);
                $buttonarray[] = $mform->createElement('submit', 'cancel' . $id, $cancel);
                break;
            default:
                break;
        }

        if (!empty($buttonarray)) {
            $mform->addGroup($buttonarray, 'statusmodgroup' . $id, '', ' ', false);
        }
    }

    /**
     * Updates the cm state with posted data.
     *
     * @param \local_extension\request $request
     * @param int $user
     * @param \stdClass $data
     * @return object
     */
    public function update_cm_state($request, $user, $data) {

        foreach ($request->mods as $id => $mod) {
            /* @var \local_extension\base_request $handler */
            $handler = $mod['handler'];

            /* @var \local_extension\cm $localcm */
            $localcm = $mod['localcm'];
            $event   = $mod['event'];
            $course  = $mod['course'];

            /*
             * Iterate over a list of states with their cmid concatenated eg. approve6
             * approve6: Would trigger the approve handler for cmid 6.
             */
            foreach ($this->statearray as $name => $state) {
                $item = $name . $id;

                if (!empty($data->$item)) {

                    // The extension has been approved. Lets hook into the handler and extend the items length.
                    if ($name == 'approve') {
                        $handler->submit_extension($event->id, $request->request->userid, $localcm->cm->data);
                    }

                    $localcm->set_state($state);

                    $status = $this->get_state_name($localcm->cm->state);

                    // After writing the history it will return the ID of the new row.
                    $history = (object) $localcm->write_history($mod, $state, $user->id);

                    $log = new \stdClass();
                    $log->status = $status;
                    $log->course = $course->fullname;
                    $log->event = $event->name;

                    $history->message = get_string('request_state_history_log', 'local_extension', $log);

                    // Update the lastmod.
                    $request->update_lastmod($user->id);

                    // You can only edit one state at a time, returning here is ok!
                    return $history;
                }

            }

        }

    }

}
