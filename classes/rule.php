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

    /** @var integer Action type: Approve. */
    const RULE_ACTION_APPROVE = 0;

    /** @var integer Action type: Subscribe. */
    const RULE_ACTION_SUBSCRIBE = 1;

    /** @var integer Condition: Less than. */
    const RULE_CONDITION_LT = 0;

    /** @var integer Condition: Greater or equal to. */
    const RULE_CONDITION_GE = 1;

    /** @var integer Condition: Special. */
    const RULE_CONDITION_SPECIAL = 2;

    /** @var integer The local_extension_trigger id */
    public $id = null;

    /** @var integer The context associated with this rule */
    public $context = null;

    /** @var string Rule name */
    public $name = null;

    /** @var integer Role that is notified */
    public $role = null;

    /** @var integer Action type */
    public $action = null;

    /** @var integer Priortiy */
    public $priority = null;

    /** @var integer Parent id */
    public $parent = null;

    /** @var integer The length from due date */
    public $lengthfromduedate = null;

    /** @var integer Length from due date type (LT/GE) */
    public $lengthtype = null;

    /** @var integer Time elapsed from request date */
    public $elapsedfromrequest = null;

    /** @var integer Time elapsed from request date type (LT/GE) */
    public $elapsedtype = null;

    /** @var stdClass Custom data assocaited with this object, serialised base64 */
    public $data = null;

    /** @var array Role names lookup */
    public $rolenames = null;

    /** @var the type name, eg. assign/quiz */
    public $datatype = null;

    /**
     * Rule object constructor.
     *
     * @param integer $ruleid
     */
    public function __construct($ruleid = null) {
        $this->id = $ruleid;
        $this->rolenames = \role_get_names(\context_system::instance(), ROLENAME_ALIAS, true);
    }

    /**
     * Parses submitted form data and sets the properties of this class to match.
     *
     * @param stdClass $form
     */
    public function load_from_form($form) {

        foreach ($form as $key => $value) {

            if (property_exists($this, $key)) {
                $this->$key = $form->$key;
            } else if (strpos($key, 'template') === 0) {
                $this->data[$key] = $value;
            }
        }

        $this->data_save();
    }

    /**
     * Unserialises and base64_decodes the saved custom data.
     *
     * @return data
     */
    public function data_load() {

        // Strict decode.
        $decoded = base64_decode($this->data, true);

        // Suppress E_NOTICE when $data can not be unserialised.
        $data = @unserialize($decoded);

        if ($data !== false) {
            return $data;
        }

        return false;
    }

    /**
     * Saves the custom data, serialising it and then base64_encoding.
     */
    public function data_save() {
        $data = serialize($this->data);

        $this->data = base64_encode($data);
    }

    /**
     * Loads the data into this object if the id has been set.
     */
    public function load() {
        global $DB;

        if (empty($this->id)) {
            throw \coding_exception('No rule id');
        }

        $ruleid = $this->id;

        $record = $DB->get_record('local_extension_triggers', array('id' => $ruleid), '*', MUST_EXIST);

        foreach ($record as $key => $value) {
            $this->$key = $record->$key;
        }

        $this->data = $this->data_load();
    }

    /**
     * Loads all rules with the type from $handler->get_name()
     *
     * @param string $type
     * @return \local_extension\rule[]
     */
    public static function load_all($type = null) {
        global $DB;

        $params = array();

        $sql = "SELECT id
                  FROM {local_extension_triggers}";

        if (!empty($type)) {
            $params = array('datatype' => $type);

            $compare = $DB->sql_compare_text('datatype') . " = " . $DB->sql_compare_text(':datatype');
            $sql .= " WHERE $compare";
        }

        $fields = $DB->get_fieldset_sql($sql, $params);

        $triggers = array();

        foreach ($fields as $id) {
            $triggers[] = self::from_id($id);
        }

        return $triggers;
    }

    /**
     * Obtain a rule object with the given id.
     *
     * @param integer $ruleid
     * @return \local_extension\rule
     */
    public static function from_id($ruleid) {
        $rule = new rule($ruleid);
        $rule->load();
        return $rule;
    }

    /**
     * Obtain a rule object with after applying the moodle data object values.
     *
     * @param stdClass $object
     * @return \local_extension\rule
     */
    public static function from_db($object) {
        $rule = new rule();

        foreach ($object as $key => $value) {
            $rule->$key = $object->$key;
        }

        $rule->data = $rule->data_load();

        return $rule;
    }

    /**
     * Returns the role name alias for the role that is associated to this class.
     * @return string
     */
    public function get_role_name() {
        return $this->rolenames[$this->role];
    }

    /**
     * Return the actions name that will be performed.
     * @return string
     */
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

    /**
     * Processes the rules associated with this object.
     *
     * @param stdClass $request
     * @param array $mod
     */
    public function process($request, $mod) {
        $event   = $mod['event'];
        $cm      = $mod['cm'];
        $localcm = $mod['localcm'];
        $course  = $mod['course'];
        $handler = $mod['handler'];

        // TODO: Check history to see if this has been triggered already.

        // If the parent has not been triggered then we abort.
        if ($this->check_parent() === false) {
            return false;
        }

        if ($this->check_request_length($request, $mod) === false) {
            return false;
        }

        if ($this->check_elapsed_length($request, $mod) === false) {
            return false;
        }

        if ($this->action == self::RULE_ACTION_APPROVE) {
            $this->approve();
        } else if ($this->action == self::RULE_ACTION_SUBSCRIBE) {
            $this->subscribe();
        }

        $templates = $this->process_templates($request, $mod);
        $this->notify_roles($mod, $templates['template_notify']);
        $this->notify_user($mod, $templates['template_user']);

        $this->get_request_time($mod);

        // Write history
    }

    private function process_templates($request, $mod) {
        global $DB;

        // A url to the status page.
        $url = new \moodle_url('/local/extension/status.php', array('id' => $request->id));

        // The user details for obtaining the full name.
        $userid = $mod['localcm']->userid;
        $user = $DB->get_record('user', array('id' => $userid));

        $templatevars = array(
            '/{{course}}/' => $mod['course']->fullname,
            '/{{module}}/' => $mod['cm']->name,
            '/{{student}}/' => \fullname($user),
            '/{{duedate}}/' => \userdate($mod['event']->timestart),
            '/{{extensiondate}}/' => \userdate($mod['localcm']->cm->data),
            '/{{requeststatusurl}}/' => $url,
            '/{{extensionlength}}/' => $this->get_request_time($mod),
        );

        $patterns = array_keys($templatevars);
        $replacements = array_values($templatevars);

        $templates = $this->get_templates();
        foreach ($templates as $key => $template) {
            $templates[$key] = preg_replace($patterns, $replacements, $template);
        }

        return $templates;
    }

    private function get_templates() {
        $templates = array (
            'template_notify' => $this->get_notify_template(),
            'template_user' => $this->get_user_template(),
        );

        return $templates;

    }

    private function get_notify_template() {
        if (!empty($this->data)) {
            if (array_key_exists('template_notify', $this->data)) {
                return $this->data['template_notify'];
            }
        }

        return false;

    }

    private function get_user_template() {
        if (!empty($this->data)) {

            if (array_key_exists('template_user', $this->data)) {
                return $this->data['template_user'];
            }

        }
        return false;

    }

    private function check_parent() {
        // TODO look up local_extension_history

        return true;

    }

    private function approve() {
    }

    private function subscribe() {
    }

    private function get_request_time($mod) {
        $localcm = $mod['localcm'];

        // The data is encoded when saving to the database, and decoded when loading from it.
        // This value will be a timestamp.
        $daterequested = $localcm->get_data();
        $datedue = $mod['event']->timestart;

        $delta = $daterequested - $datedue;

        $days = floor($delta / 60 / 60 / 24);
        $hours = ($delta - ($days * 86400)) / 60 / 60;

        // TODO lang string for this?
        $str = "{$days} {$hours}";

        return $str;
    }

    private function check_request_length($request, $mod) {
        $localcm = $mod['localcm'];

        // The data is encoded when saving to the database, and decoded when loading from it.
        // This value will be a timestamp.
        $daterequested = $localcm->get_data();
        $datedue = $mod['event']->timestart;

        $delta = $daterequested - $datedue;

        $days = $this->lengthfromduedate * 24 * 60 * 60;

        if ($this->lengthtype == self::RULE_CONDITION_LT) {
            if ($delta < $days) {
                return true;
            }

        } else if ($this->lengthtype == self::RULE_CONDITION_GE) {
            if ($delta >= $days) {
                return true;
            }
        } else {
            return false;
        }
    }

    private function check_elapsed_length($request, $mod) {
        $delta = time() - $request->timestamp;

        $days = $this->elapsedfromrequest * 24 * 60 * 60;

        if ($this->elapsedtype == self::RULE_CONDITION_LT) {
            if ($delta < $days) {
                return true;
            }

        } else if ($this->elapsedtype == self::RULE_CONDITION_GE) {
            if ($delta >= $days) {
                return true;
            }
        } else {
            return false;
        }

    }

    private function notify_roles() {
        $role = $this->role;

        // TODO notify roles
    }

    private function notify_user($mod) {
        $user = $mod['localcm']->userid;

        // TODO nofity user with template
    }

}