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

use local_extension\test\extension_testcase;
use local_extension\utility;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_utility_test extends extension_testcase {
    public function provider_for_test_it_calculates_the_number_of_weekdays() {
        return [
            ['Friday, 2018-03-02', 'Saturday, 2018-03-03', 0],
            ['Monday, 2018-03-05', 'Monday, 2018-03-05', 0],
            ['Monday, 2018-03-05', 'Tuesday, 2018-03-06', 1],
            ['Monday, 2018-03-05', 'Friday, 2018-03-09', 4],
            ['Monday, 2018-03-05', 'Saturday, 2018-03-10', 4],
            ['Monday, 2018-03-05', 'Sunday, 2018-03-11', 4],
            ['Monday, 2018-03-05', 'Monday, 2018-03-12', 5],
            ['Monday, 2018-03-05', 'Monday, 2018-03-19', 10],
            ['Saturday, 2018-03-03', 'Sunday, 2018-03-04', 0],
            ['Saturday, 2018-03-03', 'Monday, 2018-03-05', 0],
            ['Saturday, 2018-03-03', 'Tuesday, 2018-03-06', 1],
            ['Saturday, 2018-03-03', 'Wednesday, 2018-03-07', 2],
            ['Saturday, 2018-03-03', 'Thursday, 2018-03-08', 3],
            ['Saturday, 2018-03-03', 'Friday, 2018-03-09', 4],
            ['Saturday, 2018-03-03', 'Saturday, 2018-03-10', 4],
            ['Saturday, 2018-03-03', 'Monday, 2018-03-12', 5],
        ];
    }

    /**
     * @dataProvider provider_for_test_it_calculates_the_number_of_weekdays
     */
    public function test_it_calculates_the_number_of_weekdays($from, $until, $expected) {
        $message = "{$from} ~ {$until}";
        $from = $this->create_timestamp($from);
        $until = $this->create_timestamp($until);
        $actual = utility::calculate_weekdays_elapsed($from, $until);
        self::assertSame($expected, $actual, $message);
    }
}
