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

        $inprogress = $this->_customdata['inprogress'];
        $available = $this->_customdata['available'];
        $course = $this->_customdata['course'];
        $cmid = $this->_customdata['cmid'];

        $mform->addElement('hidden', 'course', $course);
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        if (!empty($inprogress)) {
            $mform->addElement('html', get_string('form_request_requestsinprogress', 'local_extension'));

            // Iterate over the current request items.
            foreach ($inprogress as $id => $mod) {
                $handler = $mod['handler'];
                $localcm = $mod['localcm'];

                $handler->status_definition($mod, $mform);
            }

            if (!empty($available)) {
                $mform->addElement('html', '<hr />');
            }
        }

        if (!empty($available)) {
            $mform->addElement('html', get_string('form_request_availablerequests', 'local_extension'));

            // Iterate over remaining available request items.
            foreach ($available as $id => $mod) {
                $handler = $mod['handler'];
                $localcm = $mod['localcm'];

                $handler->request_definition($mod, $mform);
            }

            // TODO style the width of this textarea.
            $mform->addElement('textarea', 'comment', get_string('comment', 'local_extension'), 'rows="5" cols="70"');
            $mform->addRule('comment', 'Required', 'required', null, 'client');

            $mform->addElement('filemanager', 'attachments', get_string('attachments', 'local_extension'), null, array(
                'subdirs' => 0,
            ));
            $mform->addRule('attachments', 'Required', 'required', null, 'client');
            $mform->addRule('attachments', 'Required', 'required', null, 'server');

            $this->add_action_buttons(true, get_string('submit_request', 'local_extension'));
        }

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
        $mods = $this->_customdata['available'];
        $context = $this->_customdata['context'];

        $contextlevels = array(
            CONTEXT_SYSTEM => "systemcontext",
            CONTEXT_COURSE => "coursecontext",
            CONTEXT_MODULE => "modulecontext",
        );

        $config = get_config('local_extension');
        /*
        // TODO validation
        foreach ($contextlevels as $contextlevel => $cfg) {
            if ($context->contextlevel == $contextlevel) {
               if (!empty($config->$cfg)) {
               } else {
               }
            }
        }
        */
        $due = array();
        foreach ($mods as $id => $mod) {
            $handler = $mod['handler'];
            $cm = $mod['cm'];
            $formid = 'due' . $cm->id;

            $due[$formid] = $data[$formid];

            $errors += $handler->request_validation($mform, $mod, $data);
        }

        // The array $due contains the form ids and data (request until date).
        // If each of these items is not set then return an error asking to pick at least one item.
        $hasdata = false;
        $dueerrors = array();
        foreach ($due as $formid => $data) {
            if (!empty($data)) {
                $hasdata = true;
            } else {
                $dueerrors[$formid] = get_string('error_none_selected', 'local_extension');
            }

        }

        if (!$hasdata) {
            $errors = array_merge($errors, $dueerrors);
        }

        return $errors;
    }

}

