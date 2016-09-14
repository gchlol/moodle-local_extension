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
 * Extension assignment request class.
 *
 * @package    extension_assign
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace extension_assign;

/**
 * Extension assignment request class.
 *
 * @package    extension_assign
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request extends \local_extension\base_request {

    /** @var \local_extension\rule[] $rules */
    public static $rules = null;

    /**
     * {@inheritDoc}
     * @see \local_extension\base_request::get_name()
     */
    public function get_name() {
        return 'Assignment';
    }

    /**
     * {@inheritDoc}
     * @see \local_extension\base_request::get_data_type()
     */
    public function get_data_type() {
        return 'assign';
    }

    /**
     * {@inheritDoc}
     * @see \local_extension\base_request::get_triggers()
     */
    public function get_triggers() {
        if (empty(self::$rules)) {
            self::$rules = \local_extension\rule::load_all($this->get_data_type());
        }
        return self::$rules;
    }

    /**
     * Is a calendar event something we can handle?
     *
     * @param event $event A calendar event object
     * @param coursemodule $cm A course module
     * @return boolean True if should be handled
     */
    public function is_candidate($event, $cm) {
        if ($event->eventtype == "due") {
            return true;
        }

        return false;
    }

    /**
     * Renders the request details in a form with a date selector.
     *
     * @param array $mod An array of event details
     * @param \MoodleQuickForm $mform A moodle form object
     * @return string
     */
    public function request_definition($mod, $mform = null) {
        $cm = $mod['cm'];
        $event = $mod['event'];
        $course = $mod['course'];

        $html = \html_writer::start_div('content');
        $coursestring = \html_writer::tag('b', $course->fullname . ' > ' . $event->name, array('class' => 'mod'));
        $html .= \html_writer::tag('p', $coursestring . ' ' . get_string('dueon', 'extension_assign', \userdate($event->timestart)));

        // Setup the mform due element id.
        if (!empty($mform)) {
            $html .= \html_writer::end_div(); // End .content.
            $mform->addElement('html', \html_writer::tag('p', $html));

            $startyear = date('Y');

            // TODO get assignment due date, if moves to next year, $startyear += 1.
            $stopyear = date('Y');

            $formid = 'due' . $cm->id;
            $dateconfig = array(
                'optional' => true,
                'step' => 1,
                'startyear' => $startyear,
                'stopyear' => $stopyear,
            );
            $mform->addElement('date_time_selector', $formid, get_string('requestdue', 'extension_assign'), $dateconfig);

            $mform->setDefault($formid, $event->timestart);
        }

        $html .= \html_writer::end_div(); // End .content.

        return $html;
    }

    /**
     * Renders the request status in a form with indicators of the request state.
     *
     * @param array $mod An array of event details
     * @param \MoodleQuickForm $mform A moodle form object
     * @return string
     */
    public function status_definition($mod, $mform = null) {
        $cm = $mod['cm'];
        $event = $mod['event'];
        $course = $mod['course'];
        $handler = $mod['handler'];
        $localcm = $mod['localcm'];

        $html = \html_writer::start_div('content');
        $coursestring = \html_writer::tag('b', $course->fullname . ' > ' . $event->name, array('class' => 'mod'));
        $html .= \html_writer::tag('p', $coursestring . ' ' . get_string('dueon', 'extension_assign', \userdate($event->timestart)));

        $status = $localcm->get_state_name();

        $url = new \moodle_url("/local/extension/status.php", array('id' => $localcm->requestid));
        $requeststatus = \html_writer::link($url, $status);

        // TODO case on type of request, ie. exemption, extension, etc.
        // print extension type colour like the scoping document

        $html .= \html_writer::tag('span', $requeststatus, array('class' => 'status'));
        $html .= \html_writer::tag('span', ' extension until ' . \userdate($localcm->cm->data), array('class' => 'time'));
        $html .= \html_writer::end_div(); // End .content.

        if (!empty($mform)) {
            $mform->addElement('html', $html);
        }

        return $html;
    }

    /**
     * Renders buttons that can set the status of a cm item.
     *
     * @param \MoodleQuickForm $mform A moodle form object
     * @param array $mod An array of event details
     */
    public function status_modification($mform, $mod) {
        $cm = $mod['cm'];
        $event = $mod['event'];
        $course = $mod['course'];
        $handler = $mod['handler'];

        /* @var $localcm \local_extension\cm */
        $localcm = $mod['localcm'];
        $id = $localcm->cmid;

        $buttonarray = array();

        /*
        STATE_NEW = 0;
        STATE_DENIED = 1;
        STATE_APPROVED = 2;
        STATE_REOPENED = 3;
        STATE_CANCEL = 4;
        */

        switch ($localcm->get_stateid()) {
            case $localcm::STATE_NEW:
                $buttonarray[] = &$mform->createElement('submit', 'approve' . $id, 'Approve');
                $buttonarray[] = &$mform->createElement('submit', 'deny' . $id, 'Deny');
                $buttonarray[] = &$mform->createElement('submit', 'cancel' . $id, 'Cancel');
                break;
            case $localcm::STATE_REOPENED:
                $buttonarray[] = &$mform->createElement('submit', 'approve' . $id, 'Approve');
                $buttonarray[] = &$mform->createElement('submit', 'deny' . $id, 'Deny');
                $buttonarray[] = &$mform->createElement('submit', 'cancel' . $id, 'Cancel');
                break;
            case $localcm::STATE_DENIED:
                $buttonarray[] = &$mform->createElement('submit', 'reopen' . $id, 'Reopen');
                $buttonarray[] = &$mform->createElement('submit', 'cancel' . $id, 'Cancel');
                break;
            case $localcm::STATE_CANCEL:
                $buttonarray[] = &$mform->createElement('submit', 'reopen' . $id, 'Reopen');
                break;
            case $localcm::STATE_APPROVED:
                // $buttonarray[] = &$mform->createElement('submit', 'deny' . $id, 'Deny');
                // $buttonarray[] = &$mform->createElement('submit', 'cancel' . $id, 'Cancel');
                break;
            default:
                break;

        }

        if (!empty($buttonarray)) {
            $mform->addGroup($buttonarray, 'statusmodgroup' . $id, '', ' ', false);
        }
    }

    /**
     * Validate the parts of the request form for this module
     *
     * @param \MoodleQuickForm $mform A moodle form object
     * @param array $mod An array of event details
     * @param array $data An array of form data
     * @return array of error messages
     */
    public function request_validation($mform, $mod, $data) {

        $errors = array();
        $event = $mod['event'];
        $cm = $mod['cm'];
        $formid = 'due' . $cm->id;
        $now = time();

        $due = $event->timestart;

        if (!array_key_exists($formid, $data)) {
            // Data not set.
            return $errors;
        }

        $request = $data[$formid];
        if ($request == 0) {
            // Didn't ask for extension.
            return $errors;
        }

        $toosoon = 24;

        if ($request <= $due + $toosoon * 60 * 6) {
            $errors[$formid] = get_string('dueerrortoosoon', 'extension_assign', $toosoon);
        } else if ($request <= $now) {
            $errors[$formid] = get_string('dueerrorinpast', 'extension_assign');
        }

        return $errors;
    }

    /**
     * Return data to be stored for the request
     *
     * @param \MoodleQuickForm $mform A moodle form object
     * @param array $mod An array of event details
     * @param array $data An array of form data
     * @return string The data to be stored
     */
    public function request_data($mform, $mod, $data) {
        $cm = $mod['cm'];
        $formid = 'due' . $cm->id;
        if (!empty($data->$formid)) {
            $request = $data->$formid;
            return $request;
        } else {
            return '';
        }
    }

    /**
     * Grants an extension.
     *
     * @param int $assignmentid
     * @param int $userid
     * @param int $duedate
     *
     * @return bool
     */
    public function submit_extension($assignmentid, $userid, $duedate) {
        global $CFG;
        require_once("$CFG->dirroot/mod/assign/locallib.php");

        $cm = get_coursemodule_from_instance('assign', $assignmentid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $assignment = new \assign($context, $cm, null);

        if (!$assignment->save_user_extension($userid, $duedate)) {
            return false;
        }

        return true;
    }

}
