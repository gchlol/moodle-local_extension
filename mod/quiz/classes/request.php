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
     * Is a calendar event something we can handle?
     *
     * @param event $event A calendar event object
     * @param coursemodule $cm A course module
     * @return boolean True if should be handled
     */
    public function is_candidate($event, $cm) {
        return false; // TODO
    }

    /**
     * Define parts of the request for for an event object
     *
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     */
    public function request_definition($mform, $mod) {

        // $mform->addElement('d:w', \html_writer::tag('blockquote', $html));

    }

}

