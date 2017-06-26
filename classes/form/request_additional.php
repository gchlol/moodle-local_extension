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
 * A form to enter in details to request an extension on the existing time limit.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\form;

use html_writer;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->libdir . '/formslib.php');

/**
 * A form to enter in details to request an extension on the existing time limit.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_additional extends \moodleform {

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        $request = $this->_customdata['request'];
        $cmid = $this->_customdata['cmid'];

        $mod = $request->mods[$cmid];

        $localcm = $mod->localcm;
        $course = $mod->course;
        $handler = $mod->handler;
        $stateid = $localcm->cm->state;
        $userid = $localcm->userid;

        // These hidden elements are used when clicking the 'cancel' button.
        // We request that cmid, reqid and courseid are required parameters to view the additional extension page.
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'id', $request->requestid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $course->id);
        $mform->setType('courseid', PARAM_INT);

        $html = html_writer::tag('h2', get_string('form_modify_request_header', 'local_extension'));
        $mform->addElement('html', $html);

        // Utilise the same visual modify definition that admins/coordinators view.
        $handler->modify_definition($mod, $mform, null);

        $mform->addElement('textarea', 'comment', get_string('comment', 'local_extension'), 'rows="5" cols="70"');
        $mform->addRule('comment', 'Required', 'required', null, 'client');

        $policy = get_config('local_extension', 'attachmentpolicy');
        // Moodle rich text editor may leave a <br> in an empty editor.
        if (!empty($policy)) {
            $html = html_writer::div($policy, '');
            $mform->addElement('static', 'policy', '', $html);
        }

        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'local_extension'), null, array(
            'subdirs' => 0,
        ));

        if (get_config('local_extension', 'requireattachment')) {
            $mform->addRule('attachments', 'Required', 'required', null, 'client');;
        }

        $this->add_action_buttons(true, get_string('submit_additional_request', 'local_extension'));
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
        return $errors;
    }

}

