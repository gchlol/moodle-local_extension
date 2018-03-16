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

use local_extension\navigation\extension_navigation;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_extension_navigation_test extends extension_testcase {
    public function test_it_checks_for_valid_users() {
        $this->resetAfterTest();

        self::assertFalse(extension_navigation::is_valid_logged_user(), 'Not logged in.');

        self::setGuestUser();
        self::assertFalse(extension_navigation::is_valid_logged_user(), 'Logged as guest.');

        self::setUser($this->getDataGenerator()->create_user());
        self::assertTrue(extension_navigation::is_valid_logged_user(), 'Logged as a user.');

        self::setAdminUser();
        self::assertTrue(extension_navigation::is_valid_logged_user(), 'Logged as a admin.');
    }
}
