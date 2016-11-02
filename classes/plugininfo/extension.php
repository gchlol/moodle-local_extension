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
 * Plugin class for extension handlers
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\plugininfo;

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin class for extension handlers
 *
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extension extends \core\plugininfo\base {

    /**
     * Return an array of instances each module request
     * @return \local_extension\base_request[] map of modules types to a handler object.
     */
    public static function get_enabled_request() {

        static $mods;

        if ($mods) {
            return $mods;
        }

        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('extension');

        $mods = array();
        foreach ($plugins as $plugin) {
            $classname = '\extension_' . $plugin->name . '\request';

            if (class_exists($classname)) {
                $instance = new $classname();
                $mods[$plugin->name] = $instance;
            }
        }

        return $mods;

    }

}

