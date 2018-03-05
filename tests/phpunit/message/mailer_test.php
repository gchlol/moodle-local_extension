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

use core\message\message;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_mailer_test extends extension_testcase {
    protected function send_message() {
        global $CFG;
        $user = core_user::get_user(2);

        if ($CFG->version >= 2015051100) {
            $message = new message();
            $message->userto = $user;
        } else {
            $message = new stdClass();
            $message->userto = $user->id;
        }

        $message->component = 'local_extension';
        $message->name = 'status';
        $message->userfrom = $user;
        $message->subject = 'Message Subject';
        $message->fullmessage = 'Message Contents';
        $message->fullmessageformat = FORMAT_PLAIN;

        message_send($message);
    }

    public function test_it_sends_message_immediately() {
        $this->resetAfterTest();

        $sink = phpunit_util::start_message_redirection();
        $this->send_message();
        phpunit_util::stop_message_redirection();

        $messages = $sink->get_messages();
        self::assertCount(1, $messages);
        $message = reset($messages);
        self::assertSame('Message Subject', $message->subject);
        self::assertSame('Message Contents', $message->fullmessage);
    }
}
