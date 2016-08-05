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
 * Rule tests.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden');

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

class local_extension_rule_testcase extends advanced_testcase {

    /** @const Default number of students to create */
    const DEFAULT_STUDENT_COUNT = 3;
    /** @const Default number of teachers to create */
    const DEFAULT_TEACHER_COUNT = 2;
    /** @const Default number of editing teachers to create */
    const DEFAULT_EDITING_TEACHER_COUNT = 2;
    /** @const Optional extra number of students to create */
    const EXTRA_STUDENT_COUNT = 40;
    /** @const Optional number of suspended students */
    const EXTRA_SUSPENDED_COUNT = 10;
    /** @const Optional extra number of teachers to create */
    const EXTRA_TEACHER_COUNT = 5;
    /** @const Optional extra number of editing teachers to create */
    const EXTRA_EDITING_TEACHER_COUNT = 5;
    /** @const Number of groups to create */
    const GROUP_COUNT = 6;

    /** @var stdClass $course New course created to hold the assignments */
    protected $course = null;

    /** @var array $teachers List of DEFAULT_TEACHER_COUNT teachers in the course */
    protected $teachers = null;

    /** @var array $editingteachers List of DEFAULT_EDITING_TEACHER_COUNT editing teachers in the course */
    protected $editingteachers = null;

    /** @var array $students List of DEFAULT_STUDENT_COUNT students in the course */
    protected $students = null;

    /** @var array $groups List of 10 groups in the course */
    protected $groups = null;


    /** @var assign $assign An instance of mod_assign to test the rules against */
    protected $assign = null;

    /**
     * Initial set up.
     */
    protected function setUp() {
        global $DB;

        parent::setup();
        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course();

        $this->teachers = array();
        for ($i = 0; $i < self::DEFAULT_TEACHER_COUNT; $i++) {
            array_push($this->teachers, $this->getDataGenerator()->create_user());
        }

        $this->editingteachers = array();
        for ($i = 0; $i < self::DEFAULT_EDITING_TEACHER_COUNT; $i++) {
            array_push($this->editingteachers, $this->getDataGenerator()->create_user());
        }

        $this->students = array();
        for ($i = 0; $i < self::DEFAULT_STUDENT_COUNT; $i++) {
            array_push($this->students, $this->getDataGenerator()->create_user());
        }

        $this->groups = array();
        for ($i = 0; $i < self::GROUP_COUNT; $i++) {
            array_push($this->groups, $this->getDataGenerator()->create_group(array('courseid'=>$this->course->id)));
        }

        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        foreach ($this->teachers as $i => $teacher) {
            $this->getDataGenerator()->enrol_user($teacher->id,
                    $this->course->id,
                    $teacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $teacher);
        }

        $editingteacherrole = $DB->get_record('role', array('shortname'=>'editingteacher'));
        foreach ($this->editingteachers as $i => $editingteacher) {
            $this->getDataGenerator()->enrol_user($editingteacher->id,
                    $this->course->id,
                    $editingteacherrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $editingteacher);
        }

        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        foreach ($this->students as $i => $student) {
            $this->getDataGenerator()->enrol_user($student->id,
                    $this->course->id,
                    $studentrole->id);
            groups_add_member($this->groups[$i % self::GROUP_COUNT], $student);
        }

        $this->setUser($this->editingteachers[0]);
        $this->setAdminUser();
        $this->create_instance();

        $this->assign = $this->create_instance(array(
            'duedate' => time(),
            'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
            'maxattempts' => 3,
            'submissiondrafts' => 1,
            'assignsubmission_onlinetext_enabled' => 1
        ));

        $this->setup_rules();

    }

    /**
     * Convenience function to create an instance of an assignment.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return assign
     */
    protected function create_instance($params=array()) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $params['course'] = $this->course->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = context_module::instance($cm->id);
        return new assign($context, $cm, $this->course);
    }

    public function setup_rules() {
        global $DB;

        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));

        $rules = array(
            (object)[
                'action' => \local_extension\rule::RULE_ACTION_APPROVE,
                'context' => 1,
                'datatype' => 'assign',
                'elapsedfromrequest' => 5,
                'elapsedtype' => \local_extension\rule::RULE_CONDITION_LT,
                'id' => 1,
                'lengthfromduedate' => 5,
                'lengthtype' => \local_extension\rule::RULE_CONDITION_LT,
                'name' => 'Rule1',
                'parent' => 0,
                'priority' => 1,
                'role' => $teacherrole->id,
                'template_notify' => '{{username}}',
                'template_user' => '{{username}}',
            ],
            (object)[
                'action' => \local_extension\rule::RULE_ACTION_APPROVE,
                'context' => 1,
                'datatype' => 'assign',
                'elapsedfromrequest' => 5,
                'elapsedtype' => \local_extension\rule::RULE_CONDITION_GE,
                'id' => 2,
                'lengthfromduedate' => 5,
                'lengthtype' => \local_extension\rule::RULE_CONDITION_GE,
                'name' => 'Rule2',
                'parent' => 1,
                'priority' => 1,
                'role' => $teacherrole->id,
                'template_notify' => '{{username}}',
                'template_user' => '{{username}}',
            ],
            (object)[
                'action' => \local_extension\rule::RULE_ACTION_APPROVE,
                'context' => 1,
                'datatype' => 'assign',
                'elapsedfromrequest' => 15,
                'elapsedtype' => \local_extension\rule::RULE_CONDITION_GE,
                'id' => 2,
                'lengthfromduedate' => 15,
                'lengthtype' => \local_extension\rule::RULE_CONDITION_GE,
                'name' => 'Rule3',
                'parent' => 1,
                'priority' => 2,
                'role' => $teacherrole->id,
                'template_notify' => '{{username}}',
                'template_user' => '{{username}}',
            ],
        );

        foreach ($rules as $data) {
            $rule = new \local_extension\rule();

            // Populates the $rule properties.
            $rule->load_from_form($data);

            $DB->insert_record('local_extension_triggers', $rule);
        }

    }

    /**
     */
    public function test_rules() {
        global $DB;

        $now = time();
        $start = $now - 86400;
        $end = $now + 86400;
        $user = $this->students[0];

        list($handlers, $mods) = \local_extension\utility::get_activities($user->id, $start, $end);

        $request = array(
            'userid' => $user->id,
            'searchstart' => $start,
            'searchend' => $end,
            'timestamp' => $now,
        );

        $request['id'] = $DB->insert_record('local_extension_request', $request);

        foreach ($mods as $cmid => $mod) {
            $course = $mod['course'];
            $handler = $mod['handler'];

            $data = time() + 86400;

            $cm = array(
                'request' => $request['id'],
                'userid' => $user->id,
                'course' => $course->id,
                'timestamp' => $now,
                'cmid' => $cmid,
                'state' => 0,
                'data' => $data,
            );

            $cm['id'] = $DB->insert_record('local_extension_cm', $cm);
        }

        $request = \local_extension\request::from_id($request['id']);
        $request->process_triggers();
    }

}

