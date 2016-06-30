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
 * local_extension plugin settings
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_extension', get_string('pluginname', 'local_extension'));

    $ADMIN->add('localplugins', $settings);

    $days = array();
    for ($c = 1; $c <= 21; $c++) {
        $days[$c] = new lang_string('numdays', '', $c);
    }

    $settings->add(new admin_setting_configselect('local_extension/searchback',
            new lang_string('searchback',         'local_extension'),
            new lang_string('searchbackhelp',     'local_extension'), 7, $days));

    $settings->add(new admin_setting_configselect('local_extension/searchforward',
            new lang_string('searchforward',      'local_extension'),
            new lang_string('searchforwardhelp',  'local_extension'), 14, $days));

}