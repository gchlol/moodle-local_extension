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
 * Status comment form
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
 * A form to enter in comments for an extension request
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update extends \moodleform {
    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        global $USER;

        $mform    = $this->_form;
        $user     = $this->_customdata['user'];
        $request  = $this->_customdata['request'];
        /* @var \local_extension_renderer $renderer  IDE hinting */
        $renderer = $this->_customdata['renderer'];
        $mods     = $request->mods;

        $state = \local_extension\state::instance();

        foreach ($mods as $id => $mod) {
            $handler = $mod['handler'];
            $handler->status_definition($mod, $mform);

            /* @var \local_extension\cm $localcm IDE hinting */
            $localcm = $mod['localcm'];
            $course = $mod['course'];
            $id = $localcm->cmid;
            $stateid = $localcm->cm->state;
            $userid = $localcm->userid;

            // The capability 'local/extension:modifyrequeststatus' allows a user to force change the status.
            $context = \context_course::instance($course->id, MUST_EXIST);
            $forcestatus = has_capability('local/extension:modifyrequeststatus', $context);

            // If the users access is either approve or force, then they can see the approval buttons.
            $approve = (\local_extension\rule::RULE_ACTION_APPROVE | \local_extension\rule::RULE_ACTION_FORCEAPPROVE);
            $access = \local_extension\rule::get_access($mod, $USER->id);

            if ($forcestatus) {
                $state->render_force_buttons($mform, $stateid, $id);

            } else if ($USER->id == $userid) {
                $state->render_owner_buttons($mform, $stateid, $id);

            } else if ($access & $approve) {
                $state->render_approve_buttons($mform, $stateid, $id);

            }

        }

        $html = '';

        if ($html .= $renderer->render_extension_attachments($request)) {
            $html .= \html_writer::start_tag('br');
        }

        $html .= \html_writer::empty_tag('p');
        $html .= $renderer->render_extension_comments($request);
        $html .= \html_writer::start_tag('br');
        $mform->addElement('html', $html);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // To identify the current user, $user equals $OUTPUT->user_picture($USER).
        $mform->addElement('html', $user);

        if ($USER->id == $request->request->userid) {
            $mform->addElement('filemanager', 'attachments', '', null, array('subdirs' => 0));
        }

        $mform->addElement('textarea', 'commentarea', '', 'wrap="virtual" rows="5" cols="70"');

        $mform->addElement('submit', 'submitcomment', get_string('submit_comment', 'local_extension'));
    }

    /**
     * This is used to update the $mform comment list after a post.
     * definition_after_data() is not suitable for this.
     *
     * $mform->_definition_finalized is set to true on the first page load.
     * After $mform->get_data() the definition_after_data() function will not be called.
     */
    public function update_comments() {
        $mform    = $this->_form;
        $request  = $this->_customdata['request'];
        $renderer = $this->_customdata['renderer'];

        $commentidx = $mform->_elementIndex['comments'];
        $mform->_elements[$commentidx]->_text = $renderer->render_extension_comments($request) . \html_writer::start_tag('br');
    }

    /**
     * Validate the parts of the request form for this module
     *
     * @param array $data An array of form data
     * @param array $files An array of form files
     * @return array of error messages
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);
        $mform = $this->_form;

        $itemid = $data['id'];

        if (!empty($data['attachments'])) {
            $draftitemid = $data['attachments'];

            $draftcontext = \context_user::instance($USER->id);
            $usercontext = \context_user::instance($this->_customdata['request']->request->userid);

            $fs = get_file_storage();
            $draftfiles = $fs->get_area_files($draftcontext->id, 'user', 'draft', $draftitemid, 'id');
            $oldfiles = $fs->get_area_files($usercontext->id, 'local_extension', 'attachments', $itemid, 'id');

            if (count($draftfiles) > 1) {
                $oldnames = array();

                foreach ($oldfiles as $file) {
                    $oldnames[] = $file->get_filename();
                }

                $duplicates = array();

                foreach ($draftfiles as $file) {
                    if (in_array($file->get_filename(), $oldnames) && !$file->is_directory()) {
                        $duplicates[] = $file->get_filename();
                    }
                }

                if (!empty($duplicates)) {
                    $res = null;

                    $wrapped = array_map(function($str) {
                        return sprintf("\"%s\"", $str);
                    }, $duplicates);

                    if (count($wrapped) > 1) {
                        $last = array_pop($wrapped);
                        $and = get_string('and', 'local_extension');
                        $res = implode($wrapped, ', ') . ' ' . $and . ' ' . $last;
                    } else {
                        $res = $wrapped[0];
                    }

                    $errors['attachments'] = get_string('form_rule_validate_duplicate_files', 'local_extension' , $res);
                }
            }
        }

        return $errors;
    }
}
