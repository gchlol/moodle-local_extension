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

        // Heading
        //$mform->addElement('header', 'header', get_string('externalrules', 'local_extension'), null, null);

        // ID
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Name
        $mform->addElement('text', 'name', get_string('form_rule_itemname', 'local_extension'), '');
        $mform->setType('name', PARAM_TEXT);

        // Extension Length
        $options = array(
                get_string('form_rule_select_daysless', 'local_extension', 3),
                get_string('form_rule_select_daysgreater', 'local_extension', 3),
                get_string('form_rule_select_daysgreater', 'local_extension', 6),
        );
        $mform->addElement('select', 'extension_length', get_string('form_rule_extensionlength', 'local_extension'), $options);

        // Time elpased
        $options = array(
                '-', // TODO lang string for n/a ?
                get_string('form_rule_select_daysgreater', 'local_extension', 3),
                get_string('form_rule_select_daysgreater', 'local_extension', 6),
        );
        $mform->addElement('select', 'time_exlapsed', get_string('form_rule_timeelapsed', 'local_extension'), $options);

        // Role action
        $options = array(
                get_string('form_rule_select_approve', 'local_extension'),
                get_string('form_rule_select_subscribe', 'local_extension'),
        );
        $mform->addElement('select', 'role_action', get_string('form_rule_action', 'local_extension'), $options);

        // Roles
        $options = role_get_names(\context_system::instance(), ROLENAME_ALIAS, true);
        $mform->addElement('select', 'role_list', get_string('form_rule_roles', 'local_extension'), $options);

        // Email template
        // TODO email subsystem templates

        // Continue
        $options = array(get_string('no'), get_string('yes'));
        $mform->addElement('select', 'continue', get_string('form_rule_continue', 'local_extension'), $options);

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