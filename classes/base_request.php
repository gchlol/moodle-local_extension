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

use context_course;
use html_writer;
use moodle_url;
use MoodleQuickForm;

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
    /** @var rule[] $rules */
    public static $rules = null;

    /**
     * A readable name.
     *
     * @return string Module name
     */
    abstract public function get_name();

    /**
     * Gets when the given activity module is due.
     *
     * @param mod_data $mod Local mod_data object with event details
     * @return int Timestamp indicating the due date for this activity.
     */
    abstract public function get_due_date($mod);

    /**
     * Data type name.
     *
     * @return string Data type name
     */
    abstract public function get_data_type();

    /**
     * Is a calendar event something we can handle?
     *
     * Only one calendar event for a given course module should ever return true.
     *
     * @param event        $event A calendar event object
     * @param coursemodule $cm    A course module
     * @return boolean True if should be handled
     */
    abstract public function is_candidate($event, $cm);

    /**
     * Define parts of the request for for an event object
     *
     * @param mod_data        $mod   Local mod_data object with event details
     * @param MoodleQuickForm $mform A moodle form object
     */
    abstract public function request_definition($mod, $mform);

    /**
     * Return data to be stored for the request
     *
     * @param MoodleQuickForm $mform A moodle form object
     * @param mod_data        $mod   Local mod_data object with event details
     * @param array           $data  An array of form data
     * @return string The data to be stored
     */
    abstract public function request_data($mform, $mod, $data);

    /**
     * Returns an array of trigger/rules for the handler type.
     *
     * @return \local_extension\rule[]
     */
    public function get_triggers() {
        if (empty(self::$rules)) {
            self::$rules = rule::load_all($this->get_data_type());
        }
        return self::$rules;
    }

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
     * @param mod_data        $mod Local mod_data object with event details
     * @param MoodleQuickForm $mform
     * @param bool            $optional
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

        $dateconfig = [
            'optional'  => $optional,
            'step'      => 1,
            'startyear' => $startyear,
            'stopyear'  => $stopyear,
        ];

        $formid = 'due' . $lcm->cmid;
        $mform->addElement('date_time_selector', $formid, get_string('requestdue', 'extension_assign'), $dateconfig);
        $mform->setDefault($formid, $defaultdate);
    }

    /**
     * Define parts of the request for for an event object
     *
     * @param mod_data        $mod   Local mod_data object with event details
     * @param MoodleQuickForm $mform A moodle form object
     * @return string
     */
    public function status_definition($mod, $mform = null) {
        return $this->default_status_definition($mod, $mform, $this->get_due_date($mod));
    }

    /**
     * Define parts of the request for for an event object
     *
     * @param mod_data        $mod   Local mod_data object with event details
     * @param MoodleQuickForm $mform A moodle form object
     * @param int             $dueon When the module was due
     * @return string HTML
     */
    public static function default_status_definition($mod, $mform, $dueon) {
        $event = $mod->event;
        $course = $mod->course;
        $localcm = $mod->localcm;
        $cmid = $localcm->cmid;

        $courselink = new moodle_url('/course/view.php', ['id' => $course->id]);
        $courselink = html_writer::link($courselink, $course->fullname);

        if (is_null($event)) {
            $eventlink = html_writer::span($mod->cm->name);
        } else {
            $eventlink = new moodle_url('/mod/' . $event->modulename . '/view.php', ['id' => $cmid]);
            $eventlink = html_writer::link($eventlink, $event->name);
        }

        $coursestring = html_writer::tag('b', $courselink . ' > ' . $eventlink);
        $coursestring = html_writer::div($coursestring, 'mod');

        if (!is_null($dueon)) {
            $dueon = get_string('dueon', 'extension_assign', userdate($dueon));
            $dueon = html_writer::div($dueon);
        }

        $coursecoordinators = self::get_course_coordinators($course);

        $html = html_writer::div($coursestring . ' ' . $dueon . $coursecoordinators,
                                 'content local_extension_title');

        if (!is_null($mform)) {
            $mform->addElement('html', $html);
        }

        return $html;
    }

    private static function get_course_coordinators($course) {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => 'coordinator']);
        if ($role === false) {
            return '';
        }

        $context = context_course::instance($course->id);

        $users = get_role_users($role->id, $context);
        if (count($users) == 0) {
            return '';
        }

        $html = html_writer::tag('strong', "{$role->name}: ");
        foreach ($users as $user) {
            $html .= html_writer::link("/user/profile.php?id={$user->id}", fullname($user));
            $html .= '; ';
        }
        $html = trim($html, ' ;');

        $html = html_writer::div($html, 'local_extension_coordinators');

        return $html;
    }
}
