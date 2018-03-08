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
    /** @var int */
    private $lastdigestrunid;

    /** @var mailer */
    private $mailer;

    /** @var stdClass */
    private $recipient;

    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest();
        self::setAdminUser();
        unset_config('noemailever');
        $this->mailer = new mailer();
        $this->create_recipient();
        $this->lastdigestrunid = null;
    }

    protected function create_queue_entry($status = mailer::STATUS_QUEUED, $contents = 'This is a test.') {
        global $DB;

        $subject = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $data = (object)[
            'status'   => $status,
            'added'    => time(),
            'userto'   => $this->recipient->id,
            'headers'  => "Custom Header: foo\nAnother: bar",
            'subject'  => $subject,
            'contents' => $contents,
        ];
        $data->id = $DB->insert_record(mailer::TABLE_DIGEST_QUEUE, $data);

        return $data;
    }

    protected function send_digest_with_sink() {
        $sink = $this->start_mail_sink();
        $this->lastdigestrunid = $this->mailer->email_digest_send();
        $sink->close();
        $messages = $sink->get_messages();
        return $messages;
    }

    protected function send_email($time = null, $subject = 'Message Subject', $body = 'Message Contents') {
        $message = $this->mailer->create_message($this->recipient->id, [], $subject, $body);

        if (!is_null($time)) {
            $this->mailer->set_time($time);
        }

        $sink = $this->start_mail_sink();
        $this->mailer->send($message);
        $sink->close();

        return $sink->get_messages();
    }

    protected function start_mail_sink() {
        // The call below tell us to not call that method directly, but the alternative does not work!
        return phpunit_util::start_message_redirection();
    }

    protected function create_recipient() {
        $this->recipient = $this->getDataGenerator()->create_user();
        return $this->recipient;
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
        self::assertLessThanOrEqual(time(), $this->mailer->get_time());
    }

    public function test_it_sends_message_immediately() {
        // Only the recipient digest mode should matter.
        (new preferences())->set(preferences::MAIL_DIGEST, true);
        (new preferences($this->recipient->id))->set(preferences::MAIL_DIGEST, false);

        $messages = $this->send_email();

        self::assertCount(1, $messages);
        $message = reset($messages);
        self::assertSame('Message Subject', $message->subject);
        self::assertSame('Message Contents', $message->fullmessage);
    }

    public function test_it_does_not_send_message_immediately() {
        // Only the recipient digest mode should matter.
        (new preferences())->set(preferences::MAIL_DIGEST, false);
        (new preferences($this->recipient->id))->set(preferences::MAIL_DIGEST, true);

        $messages = $this->send_email();

        self::assertCount(0, $messages);
    }

    public function test_it_stores_messages_in_database_for_digest_mode() {
        global $DB;

        (new preferences($this->recipient->id))->set(preferences::MAIL_DIGEST, true);

        $time = 1234567890;

        $messages = $this->send_email($time, 'Hello', 'World');
        self::assertCount(0, $messages);

        $actual = $DB->get_records(mailer::TABLE_DIGEST_QUEUE);
        self::assertCount(1, $actual);

        $actual = reset($actual);
        self::assertSame(mailer::STATUS_QUEUED, $actual->status);
        self::assertEquals($time, $actual->added);
        self::assertNull($actual->runid);
        self::assertEquals($this->recipient->id, $actual->userto);
        self::assertNotNull(2, $actual->headers);
        self::assertSame('Hello', $actual->subject);
        self::assertSame('World', $actual->contents);
    }

    public function test_it_digest_sends_emails_in_queue() {
        global $DB;

        $data = $this->create_queue_entry();

        $messages = $this->send_digest_with_sink();

        self::assertCount(1, $messages);

        $message = reset($messages);
        self::assertStringContains('test_it_digest_sends_emails_in_queue', $message->fullmessagehtml);
        self::assertStringContains('This is a test.', $message->fullmessagehtml);

        $status = $DB->get_field(mailer::TABLE_DIGEST_QUEUE, 'status', ['id' => $data->id], MUST_EXIST);
        self::assertSame(mailer::STATUS_SENT, $status);
    }

    public function test_it_does_not_send_emails_with_status_not_queued() {
        $this->create_queue_entry(mailer::STATUS_INVALID, 'Invalid message.');
        $this->create_queue_entry(mailer::STATUS_SENT, 'Sent already!');

        $messages = $this->send_digest_with_sink();

        self::assertCount(0, $messages);
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

    public function test_it_sends_all_messages_for_each_user_together() {
        $this->create_recipient();
        $this->create_queue_entry();
        $this->create_queue_entry();
        $this->create_queue_entry();

        $this->create_recipient();
        $this->create_queue_entry();
        $this->create_queue_entry();

        $messages = $this->send_digest_with_sink();
        self::assertCount(2, $messages);
    }

    public function test_it_filters_users_from_queue() {
        $a = $this->create_recipient();
        $this->create_queue_entry();
        $this->create_queue_entry();
        $this->create_queue_entry();

        $b = $this->create_recipient();
        $this->create_queue_entry();
        $this->create_queue_entry();

        $c = $this->create_recipient();
        $this->create_queue_entry();

        $expected = [$a->id, $b->id, $c->id];
        $actual = $this->mailer->fetch_digest_user_queues();
        sort($expected);
        sort($actual);
        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provider_for_test_is_enabled_setting
     */
    public function test_is_enabled_setting($setting, $expected) {
        $mailer = new mailer();
        set_config('emaildisable', $setting, 'local_extension');
        self::assertSame($expected, $mailer->is_enabled());
    }

    public function test_it_stores_the_digest_run_id() {
        global $DB;

        $this->create_queue_entry();
        $this->create_queue_entry();
        $this->create_queue_entry();
        $count = $DB->count_records(mailer::TABLE_DIGEST_QUEUE, ['runid' => null]);
        self::assertSame(3, $count, 'They should start with no id.');

        $this->send_digest_with_sink();

        $count = $DB->count_records(mailer::TABLE_DIGEST_QUEUE, ['runid' => $this->lastdigestrunid]);
        self::assertSame(3, $count, 'They all should have got the correct id.');
    }

    public function test_it_creates_a_digest_run_id() {
        global $DB;

        $this->mailer->set_time(12345);
        $id = $this->mailer->create_digest_run_id();

        $actual = $DB->get_record(mailer::TABLE_DIGEST_RUNS, ['id' => $id], '*', MUST_EXIST);
        self::assertEquals(12345, $actual->whenran);
    }

    public function test_it_creates_the_digest_message() {
        $messages = [
            (object)[
                'subject'  => 'First Message',
                'contents' => "Message 1 line 1<br>line 2<br>line 3",
            ],
            (object)[
                'subject'  => 'Second Message',
                'contents' => "Message 2 line 1<br>line 2<br>line 3<br>The End",
            ],
        ];
        $digest = $this->mailer->create_digest_message(2, $messages);
        self::assertSame('Extension status - digest: 2 message(s)', $digest->subject);
        foreach ($messages as $index => $message) {
            $contents = $digest->fullmessagehtml;
            self::assertStringContains($message->subject, $contents, "Message #{$index} subject missing");
            self::assertStringContains($message->contents, $contents, "Message #{$index} contents missing");
        }
    }

    public function test_it_does_not_send_an_empty_email_digest() {
        $this->create_queue_entry(mailer::STATUS_INVALID);
        $this->create_queue_entry(mailer::STATUS_SENT);
        $messages = $this->send_digest_with_sink();

        self::assertCount(0, $messages);
    }
}
