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

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule / Trigger class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule {

    /** @var int Action type: Default. No access */
    const RULE_ACTION_DEFAULT = 0;

    /** @var int Action type: Approve. */
    const RULE_ACTION_APPROVE = 1;

    /** @var int Action type: Subscribe. */
    const RULE_ACTION_SUBSCRIBE = 2;

    /** @var int Action type: Force the approval status. Do not downgrade to subscribe. */
    const RULE_ACTION_FORCEAPPROVE = 4;

    /** @var int Condition: Less than. */
    const RULE_CONDITION_LT = 1;

    /** @var int Condition: Greater or equal to. */
    const RULE_CONDITION_GE = 2;

    /** @var int Condition: Any. */
    const RULE_CONDITION_ANY = 4;

    /** @var int $id The local_extension_trigger id */
    public $id = null;

    /** @var int $context The context associated with this rule */
    public $context = null;

    /** @var string $name Rule name */
    public $name = null;

    /** @var int $role Role that is notified */
    public $role = null;

    /** @var int $action Action type */
    public $action = null;

    /** @var int $priority Priority */
    public $priority = null;

    /** @var int $parent Parent id */
    public $parent = null;

    /** @var int $lengthfromduedate The length from due date */
    public $lengthfromduedate = null;

    /** @var int $lengthtype Length from due date type (LT/GE) */
    public $lengthtype = null;

    /** @var int $elapsedfromrequest Time elapsed from request date */
    public $elapsedfromrequest = null;

    /** @var int $elapsedtype Time elapsed from request date type (LT/GE) */
    public $elapsedtype = null;

    /** @var stdClass $data Custom data assocaited with this object, serialised base64 */
    public $data = null;

    /** @var array $rolenames Role names lookup */
    public $rolenames = null;

    /** @var string $datatype the type name, eg. assign/quiz */
    public $datatype = null;

    /** @var array $children An array of child rules */
    public $children = null;

    /** @var rule $parentrule A reference to the parent rule */
    public $parentrule = null;

    /**
     * Rule object constructor.
     *
     * @param int $ruleid
     */
    public function __construct($ruleid = null) {
        $this->id = $ruleid;
        $this->rolenames = role_get_names(\context_system::instance(), ROLENAME_ALIAS, true);
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
     * @return bool
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
            throw new \coding_exception('No rule id');
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

        if (get_config('local_extension', 'ruleignoredatatype')) {
            $type = null;
        }

        if (!empty($type)) {
            $params = ['datatype' => $type];

            $compare = $DB->sql_compare_text('datatype') . " = " . $DB->sql_compare_text(':datatype');
            $sql .= " WHERE $compare";
        }

        $fields = $DB->get_fieldset_sql($sql, $params);

        $triggers = array();

        foreach ($fields as $id) {
            $triggers[$id] = self::from_id($id);
        }

        return $triggers;
    }

    /**
     * Obtain a rule object with the given id.
     *
     * @param int $ruleid
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
     * Returning a value of true will identify that notifications need to be sent out.
     *
     * @param request  $request
     * @param mod_data $mod
     * @param int      $currenttime
     * @return bool
     */
    public function process(&$request, $mod, $currenttime) {

        // Checks if the trigger for this cm has been activated.
        if ($this->check_history($mod)) {
            // There are times when the request length has been modified, and triggers have already been fired.

            // The result is true, the trigger has been fired.

            /* We only need to check when the request length has changed, this can be changed via:

               modify.php
               additional_request.php

               When the length has been modified we may need to reset the subscribers when processing ruleset.
            */

            // We are matching the rules request length checks. eg. Request lt/gt than 14 days.
            if ($this->check_request_length($mod) === false) {
                $this->setup_subscription($request, $mod);
            }
            return false;
        }

        // If the parent has been triggered then we abort.
        if ($this->check_parent($mod, $this->parent)) {
            return false;
        }

        if ($this->check_request_length($mod)) {
            return false;
        }

        if ($this->check_elapsed_length($request, $currenttime)) {
            return false;
        }

        $this->setup_subscription($request, $mod);

        $this->write_history($mod);

        return true;
    }

    /**
     * Obtains the level of access from the table local_extension_subscription.
     *
     * @param mod_data $mod
     * @param int $userid
     * @return mixed|boolean
     */
    public static function get_access($mod, $userid) {
        global $DB;

        $params = [
            'userid' => $userid,
            'localcmid' => $mod->localcm->cm->id,
        ];

        $select = "userid = :userid AND localcmid = :localcmid ORDER BY id ASC";

        $fields = $DB->get_fieldset_select('local_extension_subscription', 'access', $select, $params);

        // Return the last possible status for this user.
        if (!empty($fields)) {
            return array_pop($fields);
        }

        return self::RULE_ACTION_DEFAULT;
    }

    /**
     * Check if the users access is either approve or force.
     *
     * @param mod_data $mod
     * @param int $userid
     * @return bool
     */
    public static function can_approve($mod, $userid) {
        $bitmask = (self::RULE_ACTION_APPROVE | self::RULE_ACTION_FORCEAPPROVE);
        $access = self::get_access($mod, $userid);

        return (bool)($access & $bitmask);
    }

    /**
     * Helper to send notifications for roles based on the rule and request. Used when processing rules.
     *
     * @param request $request
     * @param mod_data $mod
     * @param stdClass $templates
     */
    public function send_notifications($request, $mod, $templates) {
        // Sets the request users fullname to the email from name, only for roles that get triggered.
        $requestuser = \core_user::get_user($request->request->userid);

        // This is called when processing triggers. So the user to for 'user' notifications should be the requesting user.
        $userto = $requestuser;

        // The email subject, for the moment a language string.
        $data = new stdClass();
        $data->courseshortname = $mod->course->shortname;
        $data->requestid = $request->requestid;
        $data->fullname = fullname($requestuser, true);
        $subject = get_string('email_notification_subject', 'local_extension', $data);

        // Notifying the roles.
        $rolecontent = $templates->role_content;
        if (!empty($rolecontent)) {
            $this->notify_roles($request, $subject, $rolecontent, $mod->course);
        }

        // Notifying the user.
        $usercontent = $templates->user_content;
        if (!empty($usercontent)) {
            $this->notify_user($request, $subject, $usercontent, $userto);
        }
    }

    /**
     * Returns the list of users with the input role for the scope of this category, course, cm.
     *
     * @param stdClass $course
     * @param int $role
     * @return array $users
     */
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
     * @param request $request
     * @param mod_data $mod
     */
    private function setup_subscription(&$request, $mod) {
        global $DB;

        $localcm = $mod->localcm;
        $course = $mod->course;

        $role = $this->role;

        $users = $this->rule_get_role_users($course, $role);

        // Iterate over all users in the cm's course that have the roleid $role.
        foreach ($users as $user) {
            $params = [
                'userid' => $user->id,
                'localcmid' => $localcm->cm->id,
            ];

            // Earlier code had duplicate subscription records hence the call to obtain many.
            $records = $DB->get_records('local_extension_subscription', $params, 'id ASC');

            if (empty($records)) {
                // Create a new record if it does not exist.
                $sub = new stdClass();
                $sub->access = self::RULE_ACTION_DEFAULT;
            } else {
                // We now call get_records which returns an array, so pop the last value.
                $sub = array_pop($records);
            }

            // If the action is the same we are to assume that they have been setup already.
            if ($this->action == $sub->access) {
                break;
            }

            $sub->userid = $user->id;
            $sub->localcmid = $localcm->cm->id;
            $sub->requestid = $localcm->requestid;
            $sub->lastmod = time();
            $sub->trig = $this->id;
            $sub->access = $this->action;

            if (empty($sub->id)) {
                $DB->insert_record('local_extension_subscription', $sub);
            } else {
                $DB->update_record('local_extension_subscription', $sub);
            }

            // Append the user to the subscribed list.
            $request->subscribedids[] = $sub->userid;
        }

        if (!empty($this->parentrule)) {
            $this->downgrade_status($mod, $this->parentrule);
        }
    }

    /**
     * When a rule is triggered that has parent items, we will revoke approval status to the earlier roles.
     * Unless that rule is set for FORCE_APPROVE.
     *
     * @param mod_data $mod
     * @param rule $rule
     */
    private function downgrade_status($mod, $rule) {
        global $DB;

        $localcm = $mod->localcm;
        $course = $mod->course;

        // If the rule has specified that the roles will be forced to approve, we skip downgrading the access.
        if ($rule->action != self::RULE_ACTION_FORCEAPPROVE) {

            $users = $this->rule_get_role_users($course, $rule->role);
            foreach ($users as $user) {
                $params = [
                    'userid' => $user->id,
                    'localcmid' => $localcm->cm->id,
                    'trig' => $rule->id,
                ];

                $sub = $DB->get_record('local_extension_subscription', $params);
                if (empty($sub)) {
                    continue;
                }

                if ($sub->access != self::RULE_ACTION_SUBSCRIBE) {
                    $sub->access = self::RULE_ACTION_SUBSCRIBE;
                    $sub->lastmod = time();
                    $DB->update_record('local_extension_subscription', $sub);
                }
            }
        }

        if (!empty($rule->parentrule)) {
            $this->downgrade_status($mod, $rule->parentrule);
        }

    }

    /**
     * If the localcm has a history entry then this will return true.
     * This means that the triggers/rules have been fired off and users were notified.
     *
     * We do not want to fire the trigger multiple times.
     *
     * @param mod_data $mod
     * @return boolean
     */
    private function check_history($mod) {
        global $DB;

        $localcm = $mod->localcm;

        $params = [
            'trig' => $this->id,
            'localcmid' => $localcm->cm->id,
            'requestid' => $localcm->cm->request,
            'userid' => $localcm->cm->userid,
            'state' => history::STATE_DEFAULT,
        ];

        $record = $DB->get_record('local_extension_hist_trig', $params);

        // A record has been found. Return true to stop processing the trigger.
        if (!empty($record)) {
            return true;
        }

        return false;
    }

    /**
     * The final stage when processing a rule. This will record an entry in the local_extension_hist_trig table
     * so that when processing the rule during a cron task will return false and not trigger anything.
     *
     * @param mod_data $mod
     */
    public function write_history($mod) {
        global $DB;

        $localcm = $mod->localcm;

        $history = [
            'trig' => $this->id,
            'timestamp' => time(),
            'localcmid' => $localcm->cm->id,
            'requestid' => $localcm->cm->request,
            'userid' => $localcm->cm->userid,
            'state' => history::STATE_DEFAULT
        ];

        $DB->insert_record('local_extension_hist_trig', (object)$history);
    }

    /**
     * Replaces the varaibles in each template with data and returns them.
     *
     * @param request $request
     * @param mod_data $mod
     * @param stdClass $contentchange An object with a single comment/statechange/attachment.
     * @return array
     */
    public function process_templates($request, $mod, $contentchange = null) {
        global $PAGE, $CFG;

        $event   = $mod->event;
        $cm      = $mod->cm;
        $localcm = $mod->localcm;
        $course  = $mod->course;
        $handler = $mod->handler;

        // A url to the status page.
        $url = new \moodle_url('/local/extension/status.php', ['id' => $localcm->cm->request]);

        // The user details for obtaining the full name.
        $userid = $mod->localcm->userid;

        $user = \core_user::get_user($userid);

        $renderer = $PAGE->get_renderer('local_extension');

        if (!empty($contentchange)) {
            $contenttemplate = $renderer->render_single_comment($request, $contentchange, true);
        } else {
            $contenttemplate = null;
        }

        $templatevars = [
            '/{{course}}/' => $course->fullname,
            '/{{module}}/' => $cm->name,
            '/{{student}}/' => fullname($user),
            '/{{student_first}}/' => $user->firstname,
            '/{{student_middle}}/' => $user->middlename,
            '/{{student_last}}/' => $user->lastname,
            '/{{student_alternate}}/' => $user->alternatename,
            '/{{student_idnumber}}/' => $user->idnumber,
            '/{{student_username}}/' => $user->username,
            '/{{duedate}}/' => userdate($event->timestart),
            '/{{extensiondate}}/' => userdate($localcm->cm->data),
            '/{{requeststatusurl}}/' => $url,
            '/{{extensionlength}}/' => $this->get_request_time($mod),
            '/{{rulename}}/' => $this->name,
            '/{{rolename}}/' => $this->rolenames[$this->role],
            '/{{eventname}}/' => $event->name,
            '/{{eventdescription}}/' => $event->description,
            '/{{attachments}}/' => $renderer->render_extension_attachments($request),
            '/{{fullhistory}}/' => $renderer->render_extension_comments($request, true),
            '/{{statechanges}}/' => null,
            '/{{statuspage}}/' => null,
            '/{{contentchange}}/' => $contenttemplate,
        ];

        $showuseridentityfields = explode(',', $CFG->showuseridentity);
        $protectedidentityfields = [
            '/{{student_idnumber}}/' => 'idnumber',
            '/{{student_username}}/' => 'username',
        ];

        // This will remove each token from template vars.
        foreach ($protectedidentityfields as $key => $field) {
            if (!in_array($field, $showuseridentityfields)) {
                $templatevars[$key] = '';
            }
        }

        $allroles = get_all_roles();
        foreach ($allroles as $id => $role) {
            $var = '/{{course_role_' . $role->shortname . '}}/';
            $ctx = \context_course::instance($course->id);
            $users = get_role_users($role->id, $ctx);

            $templatevars[$var] = '';
            foreach ($users as $user) {
                $url = new \moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id));
                $templatevars[$var] .= '<a href="' . $url->out(false) . '">' . fullname($user) . '</a>, ';
            }

            $templatevars[$var] = rtrim($templatevars[$var], ", ");
        }

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
     * @return array
     */
    private function get_templates() {
        $templates = array();

        // These array items are the names of form elements that are submitted and saved to the rule data.
        $items = [
            'template_notify',
            'template_user',
        ];

        if (!empty($this->data)) {

            foreach ($items as $template) {
                if (array_key_exists($template, $this->data)) {
                    $templates[$template] = $this->data[$template];
                }

            }
        }

        return $templates;

    }

    /**
     * If this rule has a parent value, we will check the history to see if that has been processed or not.
     *
     * @param mod_data $mod
     * @param int $parent
     * @return boolean
     */
    private function check_parent($mod, $parent) {
        global $DB;

        // There is no parent. Lets rock.
        if (empty($parent)) {
            return false;
        }

        $localcm = $mod->localcm;

        $params = [
            'trig' => $parent,
            'localcmid' => $localcm->cm->id,
            'requestid' => $localcm->cm->request,
            'userid' => $localcm->cm->userid,
            'state' => history::STATE_DEFAULT
        ];

        $record = $DB->get_record('local_extension_hist_trig', $params);

        // A record has been found. Return true, the parent has previously been activated!
        if (!empty($record)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the request length.
     *
     * @param mod_data $mod
     * @return string
     */
    private function get_request_time($mod) {
        return utility::calculate_length($mod->localcm->cm->length);
    }

    /**
     * Checks the rule for request list.
     *
     * @param mod_data $mod
     * @return boolean
     */
    private function check_request_length($mod) {
        $localcm = $mod->localcm;

        // This value will be a timestamp.
        $daterequested = (int)$localcm->get_data();
        $datedue = (int)$mod->event->timestart;

        // The length of the request.
        $delta = $daterequested - $datedue;

        // The length (in seconds) of the current rule.
        $days = $this->lengthfromduedate * 24 * 60 * 60;

        if ($this->lengthtype == self::RULE_CONDITION_ANY) {
            // If the condition is any, then we always process this.
            return false;

        } else if ($this->lengthtype == self::RULE_CONDITION_LT) {
            if ($delta < $days) {
                return false;
            }

        } else if ($this->lengthtype == self::RULE_CONDITION_GE) {
            if ($delta >= $days) {
                return false;
            }
        }

        // The delta check against the rule type passes.
        return true;
    }

    /**
     * Checks the rule for elapsed length.
     *
     * @param request $request
     * @param int     $currenttime Current time to consider
     * @return boolean
     */
    private function check_elapsed_length($request, $currenttime) {
        $delta = utility::calculate_weekdays_elapsed($request->request->timestamp, $currenttime);

        $days = (int)$this->elapsedfromrequest;

        if ($this->elapsedtype == self::RULE_CONDITION_ANY) {
            // If the condition is any, then we always process this.
            return false;

        } else if ($this->elapsedtype == self::RULE_CONDITION_LT) {
            if ($delta < $days) {
                return false;
            }

        } else if ($this->elapsedtype == self::RULE_CONDITION_GE) {
            if ($delta >= $days) {
                return false;
            }
        }

        // The delta check against the rule type passes.
        return true;
    }

    /**
     * Notify all users in the course, with the role that this rule specifies.
     *
     * @param request $request
     * @param string $subject
     * @param string $content
     * @param stdClass $course
     */
    private function notify_roles(request $request, $subject, $content, $course) {
        $role = $this->role;
        $users = $this->rule_get_role_users($course, $role);

        foreach ($users as $userto) {
            $this->notify_user($request, $subject, $content, $userto);
        }

    }

    /**
     * Notify the user that is assigned to this localcm based on the current rule.
     *
     * @param request $request
     * @param string $subject
     * @param string $content
     * @param stdClass $userto
     */
    private function notify_user(request $request, $subject, $content, $userto) {
        utility::send_trigger_email($request, $subject, $content, $userto);
    }

    /**
     * Internal helper function to return the type of rule length checking.
     * @param string $triggertype
     * @return string
     */
    public function rule_type($triggertype) {
        $any         = get_string('form_rule_any_value', 'local_extension');
        $greaterthan = get_string('form_rule_greater_or_equal', 'local_extension');
        $lessthan    = get_string('form_rule_less_than', 'local_extension');

        switch($triggertype) {
            case self::RULE_CONDITION_GE:
                $type = $greaterthan;
                break;
            case self::RULE_CONDITION_LT:
                $type = $lessthan;
                break;
            case self::RULE_CONDITION_ANY:
                $type = $any;
                break;
            default:
                $type = '';
                break;
        }

        return $type;
    }

    /**
     * Fire off the trigger creation event.
     *
     * @param int $id
     */
    public function trigger_create_event($id) {
        $eventdata = [
            'context' => \context_system::instance(),
            'objectid' => $id,
            'other' => [
                'datatype' => $this->datatype,
            ],
        ];

        event\trigger_create::create($eventdata)->trigger();

    }

    /**
     * Fire off the trigger disable event.
     */
    public function trigger_disable_event() {
        $eventdata = [
            'context' => \context_system::instance(),
            'objectid' => $this->id,
            'other' => [
                'datatype' => $this->datatype,
            ],
        ];

        event\trigger_disable::create($eventdata)->trigger();

    }

    /**
     * Fire off the triger update event.
     */
    public function trigger_update_event() {
        $eventdata = [
            'context' => \context_system::instance(),
            'objectid' => $this->id,
            'other' => [
                'datatype' => $this->datatype,
            ],
        ];

        event\trigger_update::create($eventdata)->trigger();

    }
}
