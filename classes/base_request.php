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
 * Extension base request class.
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Extension base request class.
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_request {
    /**
     * A readable name.
     * @return string Module name
     */
    abstract public function get_name();

    /**
     * Data type name.
     * @return string Data type name
     */
    abstract public function get_data_type();

    /**
     * Is a calendar event something we can handle?
     *
     * Only one calendar event for a given course module should ever return true.
     *
     * @param event $event A calendar event object
     * @param coursemodule $cm A course module
     * @return boolean True if should be handled
     */
    abstract public function is_candidate($event, $cm);

    /**
     * Define parts of the request for for an event object
     *
     * @param mod_data $mod Local mod_data object with event details
     * @param MoodleQuickForm $mform A moodle form object
     */
    abstract public function request_definition($mod, $mform);

    /**
     * Define parts of the request for for an event object
     *
     * @param mod_data $mod Local mod_data object with event details
     * @param MoodleQuickForm $mform A moodle form object
     */
    abstract public function status_definition($mod, $mform);

    /**
     * Return data to be stored for the request
     *
     * @param MoodleQuickForm $mform A moodle form object
     * @param mod_data $mod Local mod_data object with event details
     * @param array $data An array of form data
     * @return string The data to be stored
     */
    abstract public function request_data($mform, $mod, $data);

    /**
     * Returns an array of trigger/rules for the handler type.
     *
     * @return \local_extension\rule[]
     */
    abstract public function get_triggers();

    /**
     * Sets the extension length to the requested date.
     * This is called when the approve button is click when viewing the status forum.
     *
     * @param int $assignmentid
     * @param int $userid
     * @param int $duedate
     * @return bool
     */
    abstract public function submit_extension($assignmentid, $userid, $duedate);

    /**
     * Cancel an extension.
     * This is called when the approve button is click when viewing the status forum.
     *
     * @param int $assignmentid
     * @param int $userid
     * @return bool
     */
    abstract public function cancel_extension($assignmentid, $userid);

    /**
     * Obtains an instance of the mod.
     *
     * @param mod_data $mod Local mod_data object with event details
     */
    abstract public function get_instance($mod);

    /**
     * Obtains the timestamp date of a potential request.
     *
     * @param mod_data $mod Local mod_data object with event details
     * @return int|bool
     */
    abstract public function get_current_extension($mod);

    /**
     * Adds a date selector to the mform that it has been passed.
     *
     * @param mod_data $mod Local mod_data object with event details
     * @param MoodleQuickForm $mform
     * @param bool $optional
     */
    public function date_selector($mod, $mform, $optional = true) {
        $event = $mod->event;
        $lcm = $mod->localcm;

        $defaultdate = $event->timestart;
        $lcmdate = $lcm->get_data();

        if (!empty($lcmdate)) {
            $defaultdate = $lcmdate;
        }

        $startyear = date('Y');
        $stopyear = date('Y') + 1;

        $dateconfig = array(
            'optional' => $optional,
            'step' => 1,
            'startyear' => $startyear,
            'stopyear' => $stopyear,
        );

        $formid = 'due' . $lcm->cmid;
        $mform->addElement('date_time_selector', $formid, get_string('requestdue', 'extension_assign'), $dateconfig);
        $mform->setDefault($formid, $defaultdate);
    }
}
