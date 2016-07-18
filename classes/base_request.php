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

/**
 * Extension base request class.
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_request {

    /** @var integer New request. */
    const STATUS_NEW = 0;

    /** @var integer Denied request. */
    const STATUS_DENIED = 1;

    /** @var integer Approved request. */
    const STATUS_APPROVED = 2;

    /** @var integer Reopened request. */
    const STATUS_REOPENED = 3;

    /** @var integer Cancelled request. */
    const STATUS_CANCEL = 4;

    /**
     * Sets the state of this request.
     *
     * @param stdClass $cm local_extension_cm
     * @param integer $state
     */
    public function set_state($cm, $state) {
        global $DB;

        $cm->status = $state;
        $DB->update_record('local_extension_cm', $cm);

        \local_extension\utility::cache_invalidate_request($cm->request);
    }

    /**
     * Query the $cm and get the next available states.
     *
     * @param stdClass $cm The request cm object.
     * @return array An array of available states.
     */
    public function get_next_status($cm) {
        switch ($cm->status) {
            case self::STATUS_NEW:
                return array(self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_CANCEL);
            case self::STATUS_DENIED:
                return array(self::STATUS_REOPENED, self::STATUS_CANCEL);
            case self::STATUS_APPROVED:
                return array(self::STATUS_CANCEL, self::STATUS_REOPENED);
            case self::STATUS_REOPENED:
                return array(self::STATUS_APPROVED, self::STATUS_CANCEL, self::STATUS_DENIED);
            case self::STATUS_CANCEL:
                return array();
            default:
                return array();
        }
    }

    /**
     * Returns a human readable state name.
     *
     * @param string $status one of the state constants like STATUS_NEW.
     * @throws coding_exception
     * @return string the human-readable status name.
     */
    public function get_status_name($status) {
        switch ($status) {
            case self::STATUS_NEW:
                return \get_string('state_statusnew',      'local_extension');
            case self::STATUS_DENIED:
                return \get_string('state_statusdenied',   'local_extension');
            case self::STATUS_APPROVED:
                return \get_string('state_statusapproved', 'local_extension');
            case self::STATUS_REOPENED:
                return \get_string('state_statusreopened', 'local_extension');
            case self::STATUS_CANCEL:
                return \get_string('state_statuscancel',   'local_extension');
            default:
                throw new \coding_exception('Unknown request attempt state.');
        }
    }

    public function get_status_result($status) {
        switch ($status) {
            case self::STATUS_NEW:
            case self::STATUS_REOPENED:
                return \get_string('state_status_result_pending',   'local_extension');
            case self::STATUS_DENIED:
                return \get_string('state_status_result_denied',    'local_extension');
            case self::STATUS_APPROVED:
                return \get_string('state_status_result_approved',  'local_extension');
            case self::STATUS_CANCEL:
                return \get_string('state_status_result_cancelled', 'local_extension');
            default:
                throw new \coding_exception('Unknown request attempt state.');
        }
    }

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
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     */
    abstract public function request_definition($mform, $mod);

    /**
     * Define parts of the request for for an event object
     *
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     * @param user $user The user that is viewing the status.
     */
    abstract public function status_definition($mform, $mod, $user);

    /**
     * Return data to be stored for the request
     *
     * @param moodleform $mform A moodle form object
     * @param array $mod An array of event details
     * @param array $data An array of form data
     * @return string The data to be stored
     */
    abstract public function request_data($mform, $mod, $data);
}

