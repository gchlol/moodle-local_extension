<?php
// This file is part of Extension Plugin
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
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences {
    const MAIL_DIGEST = 'mail_digest';

    protected static $defaults = [
        self::MAIL_DIGEST => false,
    ];

    /** @var int */
    private $userid;

    public function get_user_id() {
        return $this->userid;
    }

    public function __construct($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $this->userid = $userid;
    }

    public function get($name) {
        $default = array_key_exists($name, self::$defaults) ? self::$defaults[$name] : null;
        return get_user_preferences("local_extension_{$name}", $default, $this->userid);
    }

    public function set($name, $value) {
        set_user_preferences(["local_extension_{$name}" => $value], $this->userid);
    }
}
