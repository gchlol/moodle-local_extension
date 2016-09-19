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
 * Event for updating new trigger.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event for updating new trigger.
 *
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_update extends \core\event\base {
    /**
     * {@inheritDoc}
     * @see \core\event\base::init()
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_extension_triggers';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_trigger_update', 'local_extension');
    }

    /**
     * {@inheritDoc}
     * @see \core\event\base::get_description()
     */
    public function get_description() {
        return "The user with the id '{$this->data['userid']}' has updated the trigger id {$this->data['objectid']} for type '{$this->other['datatype']}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $params = array('id' => $this->data['objectid'], 'datatype' => $this->other['datatype'], 'sesskey' => sesskey());
        return new \moodle_url('/local/extension/rules/edit.php', $params);
    }
}
