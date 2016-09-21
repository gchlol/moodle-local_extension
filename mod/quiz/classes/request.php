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
 * Extension quiz request class.
 *
 * @package    extension_quiz
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace extension_quiz;

/**
 * Extension quiz request class.
 *
 * @package    extension_quiz
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
        return 'Quiz';
    }

    /**
     * {@inheritDoc}
     * @see \local_extension\base_request::get_data_type()
     */
    public function get_data_type() {
        return 'quiz';
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
        return false; // TODO.
    }

    // TODO set the state of the cm.

    /**
     * Define parts of the request for for an event object
     *
     * @param array $mod An array of event details
     * @param \moodleform $mform A moodle form object
     */
    public function request_definition($mod, $mform = null) {

    }

    /**
     * Define parts of the request for for an event object
     *
     * @param array $mod An array of event details
     * @param \moodleform $mform A moodle form object
     */
    public function status_definition($mod, $mform = null) {

    }

    /**
     * Validate the parts of the request form for this module
     *
     * @param \moodleform $mform A moodle form object
     * @param array $mod An array of event details
     * @param array $data An array of form data
     * @return array of error messages
     */
    public function request_validation($mform, $mod, $data) {

        $errors = array();

        return $errors;
    }

    /**
     * Return data to be stored for the request
     *
     * @param \moodleform $mform A moodle form object
     * @param array $mod An array of event details
     * @param array $data An array of form data
     * @return string The data to be stored
     */
    public function request_data($mform, $mod, $data) {
        return '';
    }

    /**
     * Grants an extension.
     *
     * @param int $quizid
     * @param int $userid
     * @param int $duedate
     * @return bool
     */
    public function submit_extension($quizid, $userid, $duedate) {
        // TODO: Implement submit_extension() method.
    }

}

