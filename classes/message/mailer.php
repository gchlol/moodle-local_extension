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

use local_extension\preferences;

defined('MOODLE_INTERNAL') || die();

class mailer {
    const STATUS_INVALID = 'invalid';

    const STATUS_QUEUED = 'queued';

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
        $row = (object)[
            'status'    => self::STATUS_QUEUED,
            'added'     => $this->time,
            'sender'    => $message->userfrom->id,
            'recipient' => $message->userto->id,
            'subject'   => $message->subject,
            'message'   => $message->fullmessage,
        ];
        $DB->insert_record('local_extension_digest_queue', $row);
    }

    public function email_digest_send() {
    }

    public function email_digest_cleanup() {
    }
}
