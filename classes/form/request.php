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
 * Requests extension form
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\form;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->libdir . '/formslib.php');

/**
 * A form to enter in details for an extension
 *
 * @package    local_extension
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request extends \moodleform {

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        $mods = $this->_customdata['mods'];

        foreach ($mods as $id => $mod) {
            $handler = $mod['handler'];
            $handler->request_definition($mform, $mod);
        }

        $mform->addElement('editor', 'comment', get_string('comment', 'local_extension'), array(
            'rows' => '3',
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 0,
            'changeformat' => 0,
            'context' => null,
            'noclean' => 0,
            'trusttext' => 0,
            'enable_filemanagement' => false,
        ));
        $mform->addRule('comment', 'Required', 'required', null, 'client');

        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'local_extension'), null, array(
            'subdirs' => 0,
        ));

        $this->add_action_buttons(true, get_string('submit_request', 'local_extension'));
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
        $mods = $this->_customdata['mods'];

        // TODO make this fail validation if no handlers data is set.

        foreach ($mods as $id => $mod) {
            $handler = $mod['handler'];
            $errors += $handler->request_validation($mform, $mod, $data);
        }

        return $errors;
    }

}

