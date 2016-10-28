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
 * Modify the cm extension state.
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
 * A form to modify the state of an extension cm.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state extends \moodleform {
    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform    = $this->_form;
        $request  = $this->_customdata['request'];
        $cmid     = $this->_customdata['cmid'];
        $mods     = $request->mods;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 's');
        $mform->setType('s', PARAM_INT);

        $mod = $mods[$cmid];
        $handler = $mod['handler'];

        $html = \html_writer::tag('h2', 'State change confirmation');
        $mform->addElement('html', $html);

        $handler->status_change_definition($mod, $mform, $this->_customdata);

        $mform->addElement('textarea', 'commentarea', get_string('comments'), 'wrap="virtual" rows="5" cols="70"');
        $mform->addElement('html', \html_writer::empty_tag('br'));

        $mform->addElement('submit', 'submitbutton_status', get_string('submit_state_return_status', 'local_extension'));
        $mform->addElement('html', \html_writer::empty_tag('br'));

        $mform->addElement('submit', 'submitbutton_list', get_string('submit_state_return_list', 'local_extension'));
        $mform->addElement('html', \html_writer::empty_tag('br'));

        $mform->addElement('cancel', 'cancel', get_string('submit_state_back', 'local_extension'));
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

        $mform    = $this->_form;
        $request  = $this->_customdata['request'];
        $cmid     = $this->_customdata['cmid'];
        $mods     = $request->mods;

        $mod = $mods[$cmid];
        $handler = $mod['handler'];

        $lcm = $mod['localcm'];
        //$formid = 'due' . $lcm->cmid;

        return $errors;
    }
}
