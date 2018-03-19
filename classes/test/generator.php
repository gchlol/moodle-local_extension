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
 */

namespace local_extension\test;

use local_extension\rule;
use local_extension\state;
use stdClass;
use testing_data_generator;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../lib/testing/generator/lib.php');

/**
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator extends testing_data_generator {
    /** @var stdClass[] */
    protected $courses = [];

    /** @var stdClass[] */
    protected $users = [];

    /** @var string */
    protected $lastcoursementioned = null;

    public function get_last_course_mentioned() {
        return $this->courses[$this->lastcoursementioned];
    }

    public function get_course_id($shortname) {
        return $this->courses[$shortname]->id;
    }

    public function create_course_by_shortname($shortname, $options = []) {
        $this->lastcoursementioned = $shortname;

        if (array_key_exists($shortname, $this->courses)) {
            return $this->courses[$shortname];
        }

        $data = array_merge($options, ['shortname' => $shortname]);
        $this->courses[$shortname] = $this->create_course($data);
        return $this->courses[$shortname];
    }

    public function get_user_id($username) {
        return $this->create_user_by_username($username)->id;
    }

    public function create_user_by_username($username) {
        global $DB;

        if (array_key_exists($username, $this->users)) {
            return $this->users[$username];
        }

        if ($username === 'admin') {
            $user = get_admin();
        } else if ($username == 'guest') {
            $user = $DB->get_record('user', ['username' => 'guest'], '*', MUST_EXIST);
        } else {
            $user = $this->create_user([
                                           'username'  => $username,
                                           'password'  => $username,
                                           'firstname' => $username,
                                           'lastname'  => 'Behat',
                                       ]);
        }

        $this->users[$username] = $user;
        return $this->users[$username];
    }

    public function create_activity($course, $activity, $name) {
        $this->create_course_by_shortname($course);

        if ($activity == 'assignment') {
            $activity = 'assign';
        }

        $now = time();
        return $this->get_plugin_generator("mod_{$activity}")->create_instance(
            [
                'course'                   => $this->courses[$course]->id,
                'name'                     => $name,
                'allowsubmissionsfromdate' => $now - DAYSECS,
                'duedate'                  => $now + DAYSECS,
            ]
        );
    }

    public function enrol_user_role($user, $course, $role) {
        $this->create_user_by_username($user);
        $this->create_course_by_shortname($course);
        $this->enrol_user(
            $this->users[$user]->id,
            $this->courses[$course]->id,
            $role
        );
    }

    public function create_trigger() {
        global $DB;
        $DB->insert_record('local_extension_triggers', (object)[
            'context'            => 1,
            'name'               => 'Assignment Rule',
            'role'               => 1,
            'action'             => 1,
            'priority'           => 0,
            'parent'             => 0,
            'lengthfromduedate'  => 0,
            'lengthtype'         => 4,
            'elapsedfromrequest' => 0,
            'elapsedtype'        => 4,
            'datatype'           => 'assign',
            'data'               => base64_encode(serialize(
                                                      [
                                                          'template_notify' => [
                                                              'text'   => '<br>',
                                                              'format' => '1',
                                                          ],
                                                          'template_user'   => [
                                                              'text'   => '<br>',
                                                              'format' => '1',
                                                          ],
                                                      ])),
        ]);
    }

    public function create_extension($course, $assignment, $username) {
        global $DB;

        $userid = $this->get_user_id($username);

        $now = time();
        $request = [
            'userid'      => $userid,
            'lastmodid'   => $userid,
            'searchstart' => $now - 2 * DAYSECS,
            'searchend'   => $now + 2 * DAYSECS,
            'timestamp'   => $now,
            'lastmod'     => $now,
            'messageid'   => 0,
        ];
        $requestid = $DB->insert_record('local_extension_request', (object)$request);

        $comment = [
            'request'   => $requestid,
            'userid'    => $userid,
            'timestamp' => $now,
            'message'   => 'Please accept my request.',
        ];
        $DB->insert_record('local_extension_comment', (object)$comment);

        $data = $now + 3 * DAYSECS;
        $cm = [
            'request'   => $requestid,
            'userid'    => $userid,
            'course'    => $course->id,
            'timestamp' => $now,
            'name'      => $assignment->name,
            'cmid'      => $assignment->cmid,
            'state'     => state::STATE_NEW,
            'data'      => $data,
            'length'    => $data - $assignment->duedate,
        ];
        $cm['id'] = $DB->insert_record('local_extension_cm', (object)$cm);

        $history = new stdClass();
        $history->localcmid = $cm['cmid'];
        $history->requestid = $cm['request'];
        $history->timestamp = time();
        $history->state = state::STATE_NEW;
        $history->userid = $userid;
        $history->extlength = $cm['length'];
        $DB->insert_record('local_extension_hist_state', $history);

        $sub = new stdClass();
        $sub->userid = $userid;
        $sub->localcmid = $cm['id'];
        $sub->requestid = $cm['request'];
        $sub->lastmod = time();
        $sub->trig = null;
        $sub->access = rule::RULE_ACTION_DEFAULT;

        $DB->insert_record('local_extension_subscription', $sub);
    }
}
