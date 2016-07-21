<?php
// This file is part of Moodle - http://moodle.org/
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
 * Rule / Trigger class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension;

/**
 * Rule / Trigger class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule {

    const RULE_ACTION_APPROVE = 0;

    const RULE_ACTION_SUBSCRIBE = 1;

    const RULE_CONDITION_LT = 0;

    const RULE_CONDITION_GE = 1;

    const RULE_CONDITION_SPECIAL = 2;

    public $id = null;

    public $context = null;

    public $name = null;

    public $role = null;

    public $action = null;

    public $priority = null;

    public $parent = null;

    public $lengthfromduedate = null;

    public $lengthtype = null;

    public $elapsedfromrequest = null;

    public $elapsedtype = null;

    public $data = null;

    public $rolenames = null;

    public function __construct($ruleid = null) {
        $this->id = $ruleid;
        $this->rolenames = \role_get_names(\context_system::instance(), ROLENAME_ALIAS, true);
    }

    public function load_from_form($form) {

        foreach ($form as $key => $value) {

            if (property_exists($this, $key)) {
                $this->$key = $form->$key;

            } else {
                if($key == 'datatype') {
                    $this->data['datatype'] = $form->$key;
                }
            }

        }

        $this->data_save();

    }

    public function data_unserialise() {
        return unserialize(base64_decode($this->data));
    }

    public function data_save() {
        $this->data = base64_encode(serialize($this->data));
    }

    public function load() {
        global $DB;

        $rule->rolename = $roles[$rule->role];

        if (empty($this->id)) {
            throw \coding_exception('No rule id');
        }

        $ruleid = $this->id;

        $record = $DB->get_record('local_extension_triggers', array('id' => $ruleid), '*', MUST_EXIST);

        foreach ($record as $key => $value) {
            $rule->$key = $record->$key;
        }
    }

    public static function from_id($ruleid) {
        $rule = new rule($ruleid);
        $rule->load();
        return $rule;
    }


    public static function from_db($object) {
        $rule = new rule();

        foreach ($object as $key => $value) {
            $rule->$key = $object->$key;
        }

        $rule->data = $rule->data_unserialise();

        return $rule;
    }

    public function get_role_name() {
        return $this->rolenames[$this->role];
    }

    public function get_action_name() {
        switch($this->action) {
            case self::RULE_ACTION_APPROVE:
                return get_string('form_rule_select_approve', 'local_extension');
            case self::RULE_ACTION_SUBSCRIBE:
                return get_string('form_rule_select_subscribe', 'local_extension');
            default:
                return '';
        }
    }

}