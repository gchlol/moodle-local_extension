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

    public function test_status_db_field_size() {
        $class = new ReflectionClass(mailer::class);
        $prefix = 'STATUS_';
        $maxlength = 10; // Should match database length.
        foreach ($class->getConstants() as $name => $value) {
            if (substr($name, 0, strlen($prefix)) != $prefix) {
                continue;
            }
            self::assertLessThanOrEqual($maxlength, strlen($value), "Constant '{$name}' too big for field.'");
        }
    }

    public function test_status_db_default_status() {
        // This is the default status for the table, although it should never be used outside tests.
        self::assertSame('invalid', mailer::STATUS_INVALID);
    }

    public function test_it_uses_a_single_time() {
        $mailer = new mailer();
        self::assertLessThanOrEqual(time(), $mailer->get_time());
    }

    protected function send_email($time = null, $subject = 'Message Subject', $body = 'Message Contents') {
        global $CFG, $USER;
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
        $message->userfrom = $USER;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;

        $mailer = new mailer();
        if (!is_null($time)) {
            $mailer->set_time($time);
        }

        // The call below tell us to not call that method directly, but the alternative does not work!
        $sink = phpunit_util::start_message_redirection();
        $mailer->send($message);
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

    public function test_it_stores_messages_in_database_for_digest_mode() {
        global $DB;

        (new preferences())->set(preferences::MAIL_DIGEST, true);

        $time = 1234567890;

        $messages = $this->send_email($time, 'Hello', 'World');
        self::assertCount(0, $messages);

        $actual = $DB->get_records('local_extension_digest_queue');
        self::assertCount(1, $actual);
        $actual = reset($actual);
        self::assertSame(mailer::STATUS_QUEUED, $actual->status);
        self::assertEquals($time, $actual->added);
        self::assertNull($actual->sentid);
        self::assertEquals(2, $actual->sender);
        self::assertEquals($this->recipient->id, $actual->recipient);
        self::assertSame('Hello', $actual->subject);
    }

    public function test_it_digest_sends_emails_in_queue() {
        $this->markTestSkipped('Test/Feature not yet implemented.');
    }

    public function test_it_saves_in_database_the_sending_summary() {
        $this->markTestSkipped('Test/Feature not yet implemented.');
    }

    public function test_it_deletes_old_messages_from_queue() {
        $this->markTestSkipped('Test/Feature not yet implemented.');
    }

    public function provider_for_test_is_enabled_setting() {
        return [
            [null, true],
            [false, true],
            ['0', true],
            [true, false],
            ['1', false],
        ];
    }

    /**
     * @dataProvider provider_for_test_is_enabled_setting
     */
    public function test_is_enabled_setting($setting, $expected) {
        $mailer = new mailer();
        set_config('emaildisable', $setting, 'local_extension');
        self::assertSame($expected, $mailer->is_enabled());
    }
}
