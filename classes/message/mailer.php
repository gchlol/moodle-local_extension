<?php
// This file is part of Assignment Extension Manager plugin
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

namespace local_extension\message;

use core\message\message;
use core_user;
use local_extension\preferences;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/lib.php');

class mailer {
    const STATUS_INVALID = 'invalid';

    const STATUS_QUEUED = 'queued';

    const STATUS_SENT = 'sent';

    const TABLE_DIGEST_QUEUE = 'local_extension_digest_queue';

    const TABLE_DIGEST_RUNS = 'local_extension_digest_runs';

    /** @var int Timestamp to use when sending messages. */
    protected $time;

    public function __construct() {
        $this->time = time();
    }

    public function get_time() {
        return $this->time;
    }

    public function set_time($time) {
        $this->time = $time;
    }

    public function is_enabled() {
        $setting = get_config('local_extension', 'emaildisable');
        return empty($setting);
    }

    public function send($message) {
        $preferences = new preferences($message->userto->id);
        if ($preferences->get(preferences::MAIL_DIGEST)) {
            $this->save_for_digest($message);
        } else {
            message_send($message);
        }
    }

    private function save_for_digest($message) {
        global $DB;
        $headers = $message->userfrom->customheaders;
        $row = (object)[
            'status'   => self::STATUS_QUEUED,
            'added'    => $this->time,
            'userto'   => $message->userto->id,
            'headers'  => implode("\n", $headers),
            'subject'  => $message->subject,
            'contents' => $message->fullmessage,
        ];
        $DB->insert_record(self::TABLE_DIGEST_QUEUE, $row);
    }

    public function email_digest_send() {
        global $DB;
        $runid = $this->create_digest_run_id();

        $users = $this->fetch_digest_user_queues();

        foreach ($users as $userto) {
            $emails = $DB->get_records(self::TABLE_DIGEST_QUEUE,
                                       ['status' => self::STATUS_QUEUED, 'userto' => $userto]);
            foreach ($emails as $email) {
                $email->status = self::STATUS_SENT;
                $email->runid = $runid;
                $DB->update_record(self::TABLE_DIGEST_QUEUE, $email);
            }

            $message = $this->create_digest_message(
                $userto,
                $emails
            );

            message_send($message);
        }

        return $runid;
    }

    public function email_digest_cleanup() {
    }

    public function create_message($usertoid, $headers, $subject, $htmlmessage) {
        global $CFG;

        $message = new message();
        $message->userto = core_user::get_user($usertoid);
        $message->component = 'local_extension';
        $message->name = 'status';
        $message->userfrom = $this->create_user_from($headers);
        $message->subject = $subject;
        $message->fullmessage = html_to_text($htmlmessage);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $htmlmessage;
        $message->smallmessage = '';
        $message->notification = 1;

        if ($CFG->branch >= 32) {
            $message->courseid = SITEID;
        }

        return $message;
    }

    public function create_user_from(array $headers = []) {
        // Headers to help prevent auto-responders.
        $headers[] = 'Precedence: Bulk';
        $headers[] = 'X-Auto-Response-Suppress: All';
        $headers[] = 'Auto-Submitted: auto-generated';

        $noreplyuser = core_user::get_noreply_user();

        $supportusername = get_config('local_extension', 'supportusername');
        if (empty($supportusername)) {
            $supportusername = get_string('supportusernamedefault', 'local_extension');
        }

        $noreplyuser->firstname = $supportusername;
        $noreplyuser->customheaders = $headers;

        return $noreplyuser;
    }

    public function create_digest_run_id() {
        global $DB;

        $id = $DB->insert_record(self::TABLE_DIGEST_RUNS, (object)[
            'whenran' => $this->time,
        ]);

        return $id;
    }

    public function fetch_digest_user_queues() {
        global $DB;

        $queue = $DB->get_records(
            self::TABLE_DIGEST_QUEUE,
            ['status' => self::STATUS_QUEUED],
            '',
            'DISTINCT (userto)');

        $queue = array_keys($queue);
        return $queue;
    }

    public function create_digest_message($userto, $messages) {
        $contents = '';
        foreach ($messages as $message) {
            $contents .= "<article><strong>{$message->subject}</strong><blockquote>{$message->contents}</blockquote></article>";
        }
        $contents = trim($contents);
        return $this->create_message($userto,
                                     [],
                                     get_string('digest_subject', 'local_extension', count($messages)),
                                     $contents);
    }
}
