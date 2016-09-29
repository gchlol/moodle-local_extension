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
        $event = $mod['event'];
        $course = $mod['course'];

        $html = \html_writer::start_div('content');
        $coursestring = \html_writer::tag('b', $course->fullname . ' > ' . $event->name, array('class' => 'mod'));
        $str = get_string('dueon', 'extension_assign', \userdate($event->timestart));
        $html .= \html_writer::tag('p', $coursestring . ' ' . $str);

        // Setup the mform due element id.
        if (!empty($mform)) {
            $html .= \html_writer::end_div(); // End .content.
            $mform->addElement('html', \html_writer::tag('p', $html));

            $this->date_selector($mod, $mform);
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
        $str = get_string('dueon', 'extension_assign', \userdate($event->timestart));
        $html .= \html_writer::tag('p', $coursestring . ' ' . $str);

        $status = \local_extension\state::instance()->get_state_name($localcm->cm->state);

        $obj = (object) array(
            'status' => $status,
            'date' => \userdate($localcm->cm->data),
        );
        $statusline = get_string('status_status_line', 'local_extension', $obj);

        $html .= \html_writer::tag('span', $statusline, array('class' => 'time'));
        $html .= \html_writer::end_div(); // End .content.

        if (!empty($mform)) {
            $mform->addElement('html', $html);
        }

        return $html;
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
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_instance('assign', $assignmentid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $assignment = new \assign($context, $cm, null);

        if (!$assignment->save_user_extension($userid, $duedate)) {
            return false;
        }

        return true;
    }

}
