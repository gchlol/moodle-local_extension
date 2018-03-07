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
        $preferences = new preferences();
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
        $queuemessages = $DB->get_records(self::TABLE_DIGEST_QUEUE, ['status' => self::STATUS_QUEUED]);
        foreach ($queuemessages as $queuemessage) {
            $headers = explode("\n", $queuemessage->headers);
            $message = $this->create_message(
                $queuemessage->userto,
                $headers,
                $queuemessage->subject,
                $queuemessage->contents
            );

            $queuemessage->status = self::STATUS_SENT;
            $DB->update_record(self::TABLE_DIGEST_QUEUE, $queuemessage);

            message_send($message);
        }
    }

    public function email_digest_cleanup() {
    }

    public function create_message($usertoid, $headers, $subject, $content) {
        global $CFG;

        $message = new message();
        $message->userto = core_user::get_user($usertoid);
        $message->component = 'local_extension';
        $message->name = 'status';
        $message->userfrom = $this->create_user_from($headers);
        $message->subject = $subject;
        $message->fullmessage = html_to_text($content);;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $content;
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
}
