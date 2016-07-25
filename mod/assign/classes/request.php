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
        $rules = \local_extension\rule::load_all($this->get_data_type());
        return $rules;
    }

    /**
     * Is a calendar event something we can handle?
     *
     * @param event $event A calendar event object
     * @param coursemodule $cm A course module
     * @return boolean True if should be handled
     */
    public function is_candidate($event, $cm) {
        // TODO should only be true for due dates, not for other calendar events.
        return true;
    }

    /**
     * Renders the request details in a form with a date selector.
     *
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     */
    public function request_definition($mform, $mod) {

        $cm = $mod['cm'];
        $event = $mod['event'];
        $course = $mod['course'];
        $handler = $mod['handler'];

        $html = \html_writer::tag('b', $course->fullname . ' > ' . $event->name, array('class' => 'mod'));
        $html = \html_writer::tag('p', $html . ' ' . get_string('dueon', 'extension_assign', \userdate($event->timestart)));
        $mform->addElement('html', \html_writer::tag('p', $html));

        $formid = 'due' . $cm->id;
        $mform->addElement('date_time_selector', $formid, get_string('requestdue', 'extension_assign'),
                array('optional' => true, 'step' => 1));

        $mform->setDefault($formid, $event->timestart);

    }

    /**
     * Renders the request status in a form with indicators of the request state.
     *
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     * @param user $user The user that is viewing the status.
     */
    public function status_definition($mform, $mod, $user = 0) {
        // TODO display approve/deny buttons based on capability, role and status.

        $cm = $mod['cm'];
        $event = $mod['event'];
        $course = $mod['course'];
        $handler = $mod['handler'];
        $localcm = $mod['localcm'];

        $html = \html_writer::tag('b', $course->fullname . ' > ' . $event->name, array('class' => 'mod'));
        $html = \html_writer::tag('p', $html . ' ' . get_string('dueon', 'extension_assign', \userdate($event->timestart)));
        $mform->addElement('html', \html_writer::tag('p', $html));

        // TODO depending on the url, status.php/request.php, provide a link back to the status.php page in the request status string.
        $status = $localcm->get_state_name();

        $url = new \moodle_url("/local/extension/status.php", array('id' => $localcm->requestid));
        $requeststatus = \html_writer::link($url, $status);

        // TODO case on type of request, ie. exemption, extension, etc.
        // print extension type colour like the scoping document
        $html  = \html_writer::start_tag('div', array('class' => 'content'));
        $html .= \html_writer::tag('span', $requeststatus, array('class' => 'status'));
        $html .= \html_writer::tag('span', ' extension until ' . \userdate($localcm->cm->data), array('class' => 'time'));
        $html .= \html_writer::end_div(); // End .content.

        $mform->addElement('html', \html_writer::tag('p', $html));

        $handler->get_triggers();

    }

    /**
     * Validate the parts of the request form for this module
     *
     * @param moodleform $mform A moodle form object
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
     * @param moodleform $mform A moodle form object
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

}
