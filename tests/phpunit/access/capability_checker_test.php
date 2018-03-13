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

use local_extension\access\capability_checker;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_access_cabability_checker_test extends extension_testcase {
    public function test_export_csv_capability() {
        global $DB;

        self::resetAfterTest();

        $systemcontext = context_system::instance();
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);

        $user = $this->getDataGenerator()->create_user();
        role_assign($teacherroleid, $user->id, $systemcontext);
        self::setUser($user);

        self::assertFalse(capability_checker::can_export_csv(), 'No capability by default.');

        assign_capability(capability_checker::CAPABILITY_EXPORT_REQUESTS_CSV,
                          CAP_ALLOW,
                          $teacherroleid,
                          $systemcontext);
        $systemcontext->mark_dirty();

        self::assertTrue(capability_checker::can_export_csv(), 'Capability was given.');
    }
}
