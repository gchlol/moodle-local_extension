<?php
// This file is part of Moodle Course Rollover Plugin
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
 * @codingStandardsIgnoreFile
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Mink\Exception\ExpectationException;
use local_extension\test\generator;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @SuppressWarnings(public) Allow as many methods as needed.
 */
class behat_local_extension extends behat_base {
    /** @var generator */
    private $generator;

    /**
     * @BeforeScenario
     */
    public function create_generator() {
        $this->generator = new generator();
    }

    /**
     * @Given /^the extension manager is configured +\# local_extension$/
     */
    public function theExtensionManagerIsConfiguredLocal_extension() {
        $this->generator->create_trigger();
    }

    /**
     * @Given /^I am (?:logged in as )?(?:an? )?(\w+) +\# local_extension$/
     */
    public function iAmA($user) {
        $user = $this->generator->create_user_by_username($user);
        $this->execute('behat_auth::i_log_in_as', [$user->username]);
    }

    /**
     * @Given /^I (?:am at|go to) the extension preferences page(?: again)? +\# local_extension$/
     */
    public function iAmAtTheExtensionPreferencesPageLocal_extension() {
        $this->visitPath('/local/extension/preferences.php');
    }

    /**
     * @Given /^I (select|deselect|unselect) the checkbox "([^"]*)" +\# local_extension$/
     */
    public function iSelectTheCheckboxLocal_extension($selected, $field) {
        $selected = ($selected == 'select') ? 1 : 0;
        $this->execute('behat_forms::i_set_the_field_to', [$field, $selected]);
    }

    /**
     * @Given /^the checkbox "([^"]*)" should be ((?:un)?selected) +\# local_extension$/
     */
    public function theCheckboxShouldBeSelectedLocal_extension($checkbox, $selectedornot) {
        $selected = ($selectedornot == 'selected');
        $element = $this->find_field($checkbox);

        if ($element->isChecked() != $selected) {
            throw new ExpectationException('"' . $checkbox . '" should be ' . $selectedornot, $this->getSession());
        }
    }

    /**
     * @Given /^the user "([^"]*)" is enrolled into "([^"]*)" as "([^"]*)" +\# local_extension$/
     */
    public function theUserIsEnrolledIntoAsLocal_extension($user, $course, $role) {
        $this->generator->enrol_user_role($user, $course, $role);
    }

    /**
     * @When /^I am on course "([^"]*)" page +\# local_extension$/
     */
    public function iAmOnCoursePageLocal_extension($course) {
        $this->visitPath("/course/view.php?name={$course}");
    }

    /**
     * @Given /^"([^"]*)" has an extension request for the "([^"]*)" assignment +\# local_extension$/
     */
    public function hasAnExtensionRequestForTheAssignmentLocal_extension($username, $assignment) {
        $course = $this->generator->get_last_course_mentioned();
        $assignment = $this->generator->create_activity($course->shortname, 'assign', $assignment);
        $this->generator->create_extension($course, $assignment, $username);
    }

    /**
     * @Given /^the course "([^"]*)" has an assignment "([^"]*)" +\# local_extension$/
     */
    public function theCourseHasAnAssignmentLocal_extension($course, $assignment) {
        $this->generator->create_activity($course, 'assign', $assignment);
    }
}
