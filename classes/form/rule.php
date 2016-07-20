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
 * Adpater trigger rules.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\form;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->libdir . '/formslib.php');

/**
 * A form to enter in adapter trigger rules.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends \moodleform {
    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        $data = $this->_customdata['data'];
        $parentrules = $this->_customdata['parentrules'];

        // ID
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Context
        $mform->addElement('hidden', 'context', 1);
        $mform->setType('context', PARAM_INT);

        // Edit Rule Header
        $mform->addElement('header', 'name_set', get_string('form_rule_header_edit', 'local_extension'), null, null);
        $mform->setExpanded('name_set');

        // Name
        $mform->addElement('text', 'name', get_string('form_rule_itemname', 'local_extension'), '');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');
        $mform->addHelpButton('name', 'form_rule_itemname', 'local_extension');

        $optionsless = array('');
        // Less than < days. (1-10).
        for ($i = 1; $i <= 10; $i++) {
            $optionsless[] = new \lang_string('numdays', '', $i);
        }

        $optionsgreater = array('');
        // Greater than > days (1-10).
        for ($i = 1; $i <= 10; $i++) {
            $optionsgreater[] = new \lang_string('numdays', '', $i);
        }

        $mform->addElement('header', 'extension_length_set', get_string('form_rule_header_extension_length_options', 'local_extension'), null, null);
        $mform->setExpanded('extension_length_set');

        $radioarray   = array();
        $radioarray[] = $mform->createElement('radio', 'extension_length_radio', '', get_string('radio_less', 'local_extension'), 1);
        $radioarray[] = $mform->createElement('radio', 'extension_length_radio', '', get_string('radio_equal', 'local_extension'), 0);
        $radioarray[] = $mform->createElement('radio', 'extension_length_radio', '', get_string('radio_greater', 'local_extension'), 0);
        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);

        $mform->addElement('select', 'extension_length', get_string('form_rule_extension_length', 'local_extension'), $optionsless);
        //$mform->addRule('extension_length', get_string('required'), 'required');
        $mform->addHelpButton('extension_length', 'form_rule_extension_length', 'local_extension');

        /*
        $mform->addElement('select', 'extension_length_less', get_string('form_rule_extension_length_less', 'local_extension'), $optionsless);
        $mform->addRule('extension_length_less', get_string('required'), 'required');
        $mform->addHelpButton('extension_length_less', 'form_rule_extension_length_less', 'local_extension');

        $mform->addElement('static', 'or', 'OR');

        $mform->addElement('select', 'extension_length_greater', get_string('form_rule_extension_length_greater', 'local_extension'), $optionsgreater);
        $mform->addRule('extension_length_greater', get_string('required'), 'required');
        $mform->addHelpButton('extension_length_greater', 'form_rule_extension_length_greater', 'local_extension');
        */

        $mform->addElement('header', 'general_set', get_string('form_rule_header_general_options', 'local_extension'), null, null);
        $mform->setExpanded('general_set');

        // Time elpased
        $optionselapsed = array('');
        for ($i = 1; $i <= 10; $i++) {
            $optionselapsed[] = new \lang_string('numdays', '', $i);
        }

        $mform->addElement('select', 'time_elapsed', get_string('form_rule_time_elapsed', 'local_extension'), $optionselapsed);
        $mform->addHelpButton('time_elapsed', 'form_rule_time_elapsed', 'local_extension');

        // Role action
        $options = array(
            get_string('form_rule_select_approve', 'local_extension'),
            get_string('form_rule_select_subscribe', 'local_extension'),
        );
        $mform->addElement('select', 'action', get_string('form_rule_action', 'local_extension'), $options);
        $mform->addHelpButton('action', 'form_rule_action', 'local_extension');

        // Roles
        $options = role_get_names(\context_system::instance(), ROLENAME_ALIAS, true);
        $mform->addElement('select', 'role', get_string('form_rule_roles', 'local_extension'), $options);
        $mform->addHelpButton('role', 'form_rule_roles', 'local_extension');

        // Email template
        // TODO email subsystem templates

        $mform->addElement('select', 'parent', get_string('form_rule_parent', 'local_extension'), $parentrules);
        $mform->addHelpButton('parent', 'form_rule_parent', 'local_extension');

        // Continue
        $options = array(get_string('no'), get_string('yes'));
        $mform->addElement('select', 'continue', get_string('form_rule_continue', 'local_extension'), $options);
        $mform->addHelpButton('continue', 'form_rule_continue', 'local_extension');

        // Priority
        $optionspriority = array();
        for ($i = 1; $i <= 10; $i++) {
            $optionspriority[] = $i;
        }

        $mform->addElement('select', 'priority', get_string('form_rule_priority', 'local_extension'), $optionspriority);
        $mform->addHelpButton('priority', 'form_rule_priority', 'local_extension');

        $this->add_action_buttons();
    }

    /**
     * Validate the parts of the request form for this module
     *
     * @param array $data An array of form data
     * @param array $files An array of form files
     * @return array of error messages
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $mform = $this->_form;

        return $errors;
    }

}