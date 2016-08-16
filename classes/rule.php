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

    /** @var integer Action type: Default. No access */
    const RULE_ACTION_DEFAULT = 0;

    /** @var integer Action type: Approve. */
    const RULE_ACTION_APPROVE = 1;

    /** @var integer Action type: Subscribe. */
    const RULE_ACTION_SUBSCRIBE = 2;

    /** @var integer Action type: Force the approval status. Do not downgrade to subscribe. */
    const RULE_ACTION_FORCEAPPROVE = 4;

    /** @var integer Condition: Less than. */
    const RULE_CONDITION_LT = 1;

    /** @var integer Condition: Greater or equal to. */
    const RULE_CONDITION_GE = 2;

    /** @var integer Condition: Any. */
    const RULE_CONDITION_ANY = 3;

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

    /** @var array An array of child rules */
    public $children = null;

    /** @var rule A reference to the parent rule */
    public $parentrule = null;

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
            $this->data = $data;
            return true;
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

        $this->data_load();
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

        $rule->data_load();

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
            case self::RULE_ACTION_FORCEAPPROVE:
                return get_string('form_rule_select_forceapprove', 'local_extension');
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

        // Checks if the trigger for this cm has been activated.
        if ($this->check_history($mod) === false) {
            return false;
        }

        // If the parent has not been triggered then we abort.
        if ($this->check_parent($mod, $this->parent) === false) {
            return false;
        }

        if ($this->check_request_length($request, $mod) === false) {
            return false;
        }

        if ($this->check_elapsed_length($request, $mod) === false) {
            return false;
        }

        $this->setup_subscription($mod);

        $templates = $this->process_templates($mod);

        $usercontent = $templates['template_user']['text'];
        $usersubject = $this->data['template_user_subject'];

        $rolecontent = $templates['template_notify']['text'];
        $rolesubject = $this->data['template_notify_subject'];

        $user = \core_user::get_user($mod['localcm']->userid);

        $this->notify_roles($user, $mod['course'], $rolecontent);
        $this->notify_user($user, $usercontent, $user);

        // Users have been notified and subscriptions setup. Lets write a log of firing this trigger.
        $this->write_history($mod);
    }

    /**
     * Obtains the level of access from the table local_extension_subscription.
     *
     * @param array $mod
     * @param integer $userid
     * @return mixed|boolean
     */
    public static function get_access($mod, $userid) {
        global $DB;

        $localcm = $mod['localcm'];

        $params = array(
            'userid' => $userid,
            'localcmid' => $localcm->cm->cmid,
        );

        $access = $DB->get_field('local_extension_subscription', 'access', $params);

        return $access;
    }

    private function rule_get_role_users($course, $role) {
        $users = array();

        // Obtain users with the role at course level.
        $context = \context_course::instance($course->id);
        $users += \get_role_users($role, $context);

        // Obtain users with the role at course category level.
        $contextcat = \context_coursecat::instance($course->category);
        $users += \get_role_users($role, $contextcat);

        // Obtain users with the role at site level.
        $contextsite = \context_system::instance();
        $users += \get_role_users($role, $contextsite);

        return $users;
    }

    /**
     * Using the current rule configuration, this will setup the users with specific roles to have view/modification
     * access to the localcm item.
     *
     * @param unknown $mod
     */
    private function setup_subscription($mod) {
        global $DB;

        $localcm = $mod['localcm'];
        $course = $mod['course'];

        $role = $this->role;

        $users = $this->rule_get_role_users($course, $role);

        // Iterate over all users in the cm's course that have the roleid $role.
        foreach ($users as $user) {
            $params = array(
                'userid' => $user->id,
                'localcmid' => $localcm->cm->cmid,
            );

            $sub = $DB->get_record('local_extension_subscription', $params);

            if (empty($sub)) {
                $sub = new \stdClass();
                $sub->access = self::RULE_ACTION_DEFAULT;
            }

            // If the action is the same we are to assume that they have been setup already.
            if ($this->action == $sub->access) {
                break;
            }

            $sub->userid = $user->id;
            $sub->localcmid = $localcm->cm->cmid;
            $sub->requestid = $localcm->requestid;
            $sub->lastmod = \time();
            $sub->trigger = $this->id;
            $sub->access = $this->action;

            if (empty($sub->id)) {
                $DB->insert_record('local_extension_subscription', $sub);
            } else {
                $DB->update_record('local_extension_subscription', $sub);
            }
        }

        if (!empty($this->parentrule)) {
            $this->downgrade_status($mod, $this->parentrule);
        }
    }

    /**
     * When a rule is triggered that has parent items, we will revoke approval status to the earlier roles.
     * Unless that rule is set for FORCE_APPROVE.
     *
     * @param array $mod
     * @param rule $rule
     */
    private function downgrade_status($mod, $rule) {
        global $DB;

        $localcm = $mod['localcm'];
        $course = $mod['course'];

        // If the rule has specified that the roles will be forced to approve, we skip dowgrading the acccess.
        if ($rule->action != \local_extension\rule::RULE_ACTION_FORCEAPPROVE) {

            $users = $this->rule_get_role_users($course, $rule->role);
            foreach ($users as $user) {
                $params = array(
                    'userid' => $user->id,
                    'localcmid' => $localcm->cm->cmid,
                );

                $sub = $DB->get_record('local_extension_subscription', $params);
                if (empty($sub)) {
                    continue;
                }

                if ($sub->access != \local_extension\rule::RULE_ACTION_SUBSCRIBE) {
                    $sub->access = \local_extension\rule::RULE_ACTION_SUBSCRIBE;
                    $sub->lastmod = \time();
                    $DB->update_record('local_extension_subscription', $sub);
                }
            }
        }

        if (!empty($rule->parentrule)) {
            $this->downgrade_status($mod, $rule->parentrule);
        }

    }

    /**
     * If the localcm has a history entry then this will return false.
     * This means that the triggers/rules have been fired off and users were notified.
     *
     * We do not want to fire the trigger multiple times.
     *
     * @param array $mod
     * @return boolean
     */
    private function check_history($mod) {
        global $DB;

        $localcm = $mod['localcm'];

        $params = array(
            'trigger' => $this->id,
            'localcmid' => $localcm->cm->id,
            'requestid' => $localcm->cm->request,
            'userid' => $localcm->cm->userid,
            'state' => \local_extension\history::STATE_DEFAULT,
        );

        $sql = "SELECT id
                  FROM {local_extension_history_trig}
                 WHERE trigger = :trigger
                   AND localcmid = :localcmid
                   AND requestid = :requestid
                   AND userid = :userid
                   AND state = :state";

        $record = $DB->get_record_sql($sql, $params);

        // A record has been found. Return false to stop processing the trigger.
        if (!empty($record)) {
            return false;
        }

        return true;
    }

    /**
     * The final stage when processing a rule. This will record an entry in the local_extension_history_trig table
     * so that when processing the rule during a cron task will return false and not trigger anything.
     *
     * @param array $mod
     */
    private function write_history($mod) {
        global $DB;

        $localcm = $mod['localcm'];

        $history = array(
            'trigger' => $this->id,
            'timestamp' => time(),
            'localcmid' => $localcm->cm->id,
            'requestid' => $localcm->cm->request,
            'userid' => $localcm->cm->userid,
            'state' => \local_extension\history::STATE_DEFAULT
        );

        $DB->insert_record('local_extension_history_trig', $history);
    }

    /**
     * Replaces the varaibles in each template with data and returns them.
     *
     * @param array $mod
     * @return mixed|boolean[]|\local_extension\stdClass[]
     */
    private function process_templates($mod) {
        global $DB;

        $event   = $mod['event'];
        $cm      = $mod['cm'];
        $localcm = $mod['localcm'];
        $course  = $mod['course'];

        // A url to the status page.
        $url = new \moodle_url('/local/extension/status.php', array('id' => $localcm->cm->request));

        // The user details for obtaining the full name.
        $userid = $mod['localcm']->userid;

        $user = \core_user::get_user($userid);

        $templatevars = array(
            '/{{course}}/' => $course->fullname,
            '/{{module}}/' => $cm->name,
            '/{{student}}/' => \fullname($user),
            '/{{student_first}}/' => $user->firstname,
            '/{{student_middle}}/' => $user->middlename,
            '/{{student_last}}/' => $user->lastname,
            '/{{student_alternate}}/' => $user->alternatename,
            '/{{duedate}}/' => \userdate($event->timestart),
            '/{{extensiondate}}/' => \userdate($localcm->cm->data),
            '/{{requeststatusurl}}/' => $url,
            '/{{extensionlength}}/' => $this->get_request_time($mod),
            '/{{rulename}}/' => $this->name,
            '/{{role}}/' => $this->rolenames[$this->role],
            '/{{eventname}}/' => $event->name,
            '/{{eventdescription}}/' => $event->description,
        );

        // TODO:
        /*
        additional name options
        addition course options
        etc. more template varaibles
        */

        $patterns = array_keys($templatevars);
        $replacements = array_values($templatevars);

        $templates = $this->get_templates();
        foreach ($templates as $key => $template) {
            $templates[$key] = preg_replace($patterns, $replacements, $template);
        }

        return $templates;
    }

    /**
     * Obtains the templates.
     *
     * @return boolean[]|\local_extension\stdClass[]
     */
    private function get_templates() {
        $templates = array (
            'template_notify' => $this->get_notify_template(),
            'template_user' => $this->get_user_template(),
        );

        return $templates;

    }

    /**
     * Obtains the notify template.
     *
     * @return \local_extension\stdClass|boolean
     */
    private function get_notify_template() {
        if (!empty($this->data)) {
            if (array_key_exists('template_notify', $this->data)) {
                return $this->data['template_notify'];
            }
        }

        return false;

    }

    /**
     * Obtains the user_template.
     *
     * @return \local_extension\stdClass|boolean
     */
    private function get_user_template() {
        if (!empty($this->data)) {

            if (array_key_exists('template_user', $this->data)) {
                return $this->data['template_user'];
            }

        }
        return false;

    }

    /**
     * If this rule has a parent value, we will check the history to see if that has been processed or not.
     *
     * @param array $mod
     * @param integer $parent
     * @return boolean
     */
    private function check_parent($mod, $parent) {
        global $DB;

        // There is no parent. Lets rock.
        if (empty($parent)) {
            return true;
        }

        $localcm = $mod['localcm'];

        $params = array(
            'trigger' => $parent,
            'localcmid' => $localcm->cm->id,
            'requestid' => $localcm->cm->request,
            'userid' => $localcm->cm->userid,
            'state' => \local_extension\history::STATE_DEFAULT
        );

        $record = $DB->get_record('local_extension_history_trig', $params);

        // A record has been found. Return true, the parent has previously been activated!
        if (!empty($record)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the request length.
     *
     * @param array $mod
     * @return string
     */
    private function get_request_time($mod) {
        $localcm = $mod['localcm'];

        // The data is encoded when saving to the database, and decoded when loading from it.
        // This value will be a timestamp.
        $daterequested = $localcm->get_data();
        $datedue = $mod['event']->timestart;

        $delta = $daterequested - $datedue;

        $days = floor($delta / 60 / 60 / 24);
        $hours = floor(($delta - ($days * 86400)) / 60 / 60);

        // TODO lang string for this?
        $str = "{$days} {$hours}";

        return $str;
    }

    /**
     * Checks the rule for request list.
     *
     * @param \local_extension\request $request
     * @param array $mod
     * @return boolean
     */
    private function check_request_length($request, $mod) {
        $localcm = $mod['localcm'];

        // The data is encoded when saving to the database, and decoded when loading from it.
        // This value will be a timestamp.
        $daterequested = $localcm->get_data();
        $datedue = $mod['event']->timestart;

        $delta = $daterequested - $datedue;

        $days = $this->lengthfromduedate * 24 * 60 * 60;

        if ($this->elapsedtype == self::RULE_CONDITION_ANY) {
            return true;
        } else if ($this->lengthtype == self::RULE_CONDITION_LT) {
            if ($delta < $days) {
                return true;
            }

        } else if ($this->lengthtype == self::RULE_CONDITION_GE) {
            if ($delta >= $days) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks the rule for elapsed lenth.
     *
     * @param \local_extension\request $request
     * @param array $mod
     * @return boolean
     */
    private function check_elapsed_length($request, $mod) {
        $delta = time() - $request->timestamp;

        $days = $this->elapsedfromrequest * 24 * 60 * 60;

        if ($this->elapsedtype == self::RULE_CONDITION_ANY) {
            return true;
        } else if ($this->elapsedtype == self::RULE_CONDITION_LT) {
            if ($delta < $days) {
                return true;
            }

        } else if ($this->elapsedtype == self::RULE_CONDITION_GE) {
            if ($delta >= $days) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notify all users in the course, with the role that this rule specifies.
     *
     * @param stdClass $user
     * @param stdClass $course
     * @param array $template
     */
    private function notify_roles($user, $course, $template) {
        $role = $this->role;

        $users= $this->rule_get_role_users($course, $role);

        foreach ($users as $emailto) {
            $this->notify_user($user, $template, $emailto);
        }

    }

    /**
     * Notify the user that is assigned to this localcm based on the current rule.
     *
     * @param stdClass $user
     * @param array $template
     * @param stdClass $emailto
     */
    private function notify_user($user, $template, $emailto) {
        $subject = "Extension request for " . \fullname($user);
        \local_extension\utility::send_trigger_email($subject, $template, $emailto);
    }

}