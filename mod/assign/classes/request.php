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

    // TODO set the state of the cm

    /**
     * Define parts of the request for for an event object
     *
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     */
    public function request_definition($mform, $mod) {

        $cm = $mod['cm'];
        $event = $mod['event'];
        $course = $mod['course'];

        $html = \html_writer::tag('b', $course->fullname . ' > ' . $event->name, array('class' => 'mod'));
        $html = \html_writer::tag('p', $html . ' ' . get_string('dueon', 'extension_assign', \userdate($event->timestart)));
        $mform->addElement('html', \html_writer::tag('p', $html));

        $formid = 'due' . $cm->id;
        $mform->addElement('date_time_selector', $formid, get_string('requestdue', 'extension_assign'),
                array('optional' => true, 'step' => 1));

        $mform->setDefault($formid, $event->timestart);
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
        $request = $data->$formid;
        return $request;
    }

    /**
     * Render output for the status page.
     *
     * @param stdClass $cm Extension cm data.
     * @param stdClass $course Course data.
     * @param request $request Request data.
     * @return string $out The html output.
     */
    public function render_status($cm, $course, $request) {
        $out = '';
        $out .= \html_writer::div($course->fullname, 'assigncm');
        $out .= \html_writer::div($cm->name, 'assigncm');
        return $out;
    }

}

