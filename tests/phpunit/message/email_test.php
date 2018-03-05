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

use local_extension\mod_data;
use local_extension\rule;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_email_test extends extension_testcase {
    public function test_the_subject_has_the_course_shortname() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['shortname' => 'XYZ_TEST']);
        $user = $this->getDataGenerator()->create_user();
        $notifyuser = $this->getDataGenerator()->create_user();

        $rule = new rule();

        $request = $this->create_request($user->id);
        $request->subscribedids[] = $notifyuser->id;

        $mod = new mod_data();
        $mod->course = $course;

        $request->mods = [$mod];

        $templates = (object)['role_content' => '', 'user_content' => 'send it'];

        $history = [
            (object)[
                'request'   => $request->requestid,
                'userid'    => $user->id,
                'timestamp' => time(),
                'message'   => 'Testing',
                'id'        => 0,
            ],
        ];

        $sink = phpunit_util::start_message_redirection();
        $rule->send_notifications($request, $mod, $templates); // Send rule notifications.
        $request->notify_subscribers($history, $user->id);// Send subscriber notifications.
        phpunit_util::stop_message_redirection();

        self::assertCount(2, $sink->get_messages());
        foreach ($sink->get_messages() as $key => $message) {
            self::assertContains('XYZ_TEST', $message->subject, "Message #[{$key}]");
        }
    }
}
