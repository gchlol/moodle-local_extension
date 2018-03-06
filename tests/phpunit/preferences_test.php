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

use local_extension\preferences;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_preferences_test extends extension_testcase {
    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest();
        self::setAdminUser();
    }

    public function test_it_creates_with_userid() {
        global $USER;

        $tests = [
            [null, $USER->id],
            [999, 999],
        ];

        foreach ($tests as $key => list($parameter, $expected)) {
            $manager = new preferences($parameter);
            self::assertSame($expected, $manager->get_user_id(), "Test #{$key}");
        }
    }

    public function test_it_sets_for_current_user() {
        (new preferences())->set('name', 'abc123');
        self::assertSame('abc123', get_user_preferences('local_extension_name'));
    }

    public function test_it_gets_for_current_user() {
        set_user_preferences(['local_extension_name' => 'john']);
        $got = (new preferences())->get('name');
        self::assertSame('john', $got);
    }

    public function test_it_sets_for_another_user() {
        $user = $this->getDataGenerator()->create_user();
        (new preferences($user->id))->set('name', 'abc123');
        self::assertSame('abc123', get_user_preferences('local_extension_name', null, $user));
    }

    public function test_it_gets_for_another_user() {
        $user = $this->getDataGenerator()->create_user();
        set_user_preferences(['local_extension_name' => 'john'], $user);
        $got = (new preferences($user->id))->get('name');
        self::assertSame('john', $got);
    }

    public function test_it_has_defaults() {
        $expectations = [
            'invalid'                => null,
            preferences::MAIL_DIGEST => false,
        ];
        foreach ($expectations as $preference => $expectation) {
            $actual = (new preferences())->get($preference);
            self::assertSame($expectation, $actual, "Preference: {$preference}");
        }
    }
}
