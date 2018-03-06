<?php
// This file is part of Moodle Assignment Extension Plugin
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
 * @copyright   2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_extension\preferences_manager;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_preferences_manager_test extends extension_testcase {
    public function test_it_sets() {
        preferences_manager::set('name', 'abc123');
        self::assertSame('abc123', get_user_preferences('local_extension_name'));
    }

    public function test_it_gets() {
        set_user_preferences(['local_extension_name' => 'john']);
        $got = preferences_manager::get('name');
        self::assertSame('john', $got);
    }

    public function test_it_has_defaults() {
        $expectations = [
            'invalid'                        => null,
            preferences_manager::MAIL_DIGEST => false,
        ];
        foreach ($expectations as $preference => $expectation) {
            $actual = preferences_manager::get($preference);
            self::assertSame($expectation, $actual, "Preference: {$preference}");
        }
    }
}
