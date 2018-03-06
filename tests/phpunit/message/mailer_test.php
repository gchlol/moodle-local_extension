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
use local_extension\message\mailer;
use local_extension\preferences;
use local_extension\test\extension_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2017 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_mailer_test extends extension_testcase {
    private $recipient;

    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest();
        self::setAdminUser();
        unset_config('noemailever');
        $this->recipient = $this->getDataGenerator()->create_user(['email' => 'destination@extension.test']);
    }

    protected function send_email() {
        global $CFG;
        $user = $this->recipient;

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

        // The call below tell us to not call that method directly, but the alternative does not work!
        $sink = phpunit_util::start_message_redirection();
        (new mailer())->send($message);
        $sink->close();

        return $sink->get_messages();
    }

    public function test_it_sends_message_immediately() {
        $messages = $this->send_email();

        self::assertCount(1, $messages);
        $message = reset($messages);
        self::assertSame('Message Subject', $message->subject);
        self::assertSame('Message Contents', $message->fullmessage);
    }

    public function test_it_does_not_send_message_immediately() {
        (new preferences())->set(preferences::MAIL_DIGEST, true);

        $messages = $this->send_email();

        self::assertCount(0, $messages);
    }
}
