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
 * Status page in local_extension
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
global $PAGE, $USER;

require_login(false);

// TODO add perms checks here.

$requestid = required_param('id', PARAM_INTEGER);

$url = new moodle_url('/local/extension/status.php', array('id' => $requestid));
$PAGE->set_url($url);

// TODO context could be user, course or module.
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('status_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$request = \local_extension\utility::cache_get_request($requestid);
$fileareaitemid = $request->request->timestamp . $requestid;

$renderer = $PAGE->get_renderer('local_extension');

$params = array(
    'user' => $OUTPUT->user_picture($USER),
    'request' => $request,
    'renderer' => $renderer,
    'history' => $request->state_history()
);

$mform = new \local_extension\form\update(null, $params);

if ($form = $mform->get_data()) {
    $comment = $form->commentarea;

    $usercontext = context_user::instance($request->request->userid);

    $itemid = $requestid;
    $draftitemid = file_get_submitted_draft_itemid('attachments');

    $fs = get_file_storage();
    $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id');
    $oldfiles = $fs->get_area_files($usercontext->id, 'local_extension', 'attachments', $itemid, 'id');

    // File count must be greater that 1, as an item is the directory '.'.
    if (count($draftfiles) > 1) {

        // We need to add the existing files to the draft area so they are saved and merged with the new area.
        foreach ($oldfiles as $oldfile) {
            $filerecord = new stdClass();
            $filerecord->contextid = $usercontext->id;
            $filerecord->component = 'user';
            $filerecord->filearea ='draft';
            $filerecord->itemid = $draftitemid;

            // Check if see if the pathname hash exsits before adding the file.

            $hash = $fs->get_pathname_hash(
                $usercontext->id,
                'user',
                'draft',
                $draftitemid,
                $oldfile->get_filepath(),
                $oldfile->get_filename()
            );

            // We do not delete / update / modify the old file.
            if (!array_key_exists($hash, $draftfiles)) {
                $fs->create_file_from_storedfile($filerecord, $oldfile);
            } else {
                // TODO Provide notification that files are not replaced?
            }
        }

        file_save_draft_area_files($draftitemid, $usercontext->id, 'local_extension', 'attachments', $itemid);
    }

    // Parse the form data to see if any accept/deny/reopen/etc buttons have been clicked, and update the state accordingly.
    $request->update_cm_state($USER, $form);

    if (!empty($comment)) {
        $request->add_comment($USER, $comment);
    }

    // Invalidate the cache for this request. The content has changed.
    $request->get_data_cache()->delete($request->requestid);

    redirect($url);
} else {
    $usercontext = context_user::instance($USER->id);

    $draftitemid = 0;
    file_prepare_draft_area($draftitemid, $usercontext->id, 'local_extension', 'attachments', null);

    $entry = new stdClass();
    $entry->attachments = $draftitemid;
    $mform->set_data($entry);

    $data = array('id' => $requestid);
    $mform->set_data($data);
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();