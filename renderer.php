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
 *  local_extension plugin renderer
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

/**
 * Extension renderer class.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_extension_renderer extends plugin_renderer_base {

    /**
     * Extension status renderer.
     *
     * @param request $req The extension request object.
     * @return string $out The html output.
     */
    public function render_extension_status(\local_extension\request $req) {
    }

    /**
     * Extension comment renderer.
     *
     * @param request $req The extension request object.
     * @return string $out The html output.
     */
    public function render_extension_comments(\local_extension\request $req) {
        $out = '';

        $out .= html_writer::start_tag('div', array('class' => 'comments'));
        foreach ($req->comments as $comment) {
            $user = $req->users[$comment->userid];

            $out .= html_writer::start_tag('div', array('class' => 'comment'));

            $out .= html_writer::start_tag('div', array('class' => 'avatar'));
            $out .= $this->output->user_picture($user, array(
                'size' => 50,
            ));
            $out .= html_writer::end_div(); // End .avatar.

            $out .= html_writer::start_tag('div', array('class' => 'content'));
            $out .= html_writer::tag('span', fullname($user), array('class' => 'name'));

            $context = 1; // TODO what context is this in relation to? Usually one a cm.
            $role = 'Course coordinator'; // TODO look this up.
            $out .= html_writer::tag('span', ' - ' . $role, array('class' => 'role'));
            $out .= html_writer::tag('span', ' - ' . $this->render_time($comment->timestamp), array('class' => 'time'));

            $out .= html_writer::start_tag('div', array('class' => 'message'));
            $out .= html_writer::div(format_text(trim($comment->message), FORMAT_MOODLE), 'comment');
            $out .= html_writer::end_div(); // End .message.
            $out .= html_writer::end_div(); // End .content.
            $out .= html_writer::end_div(); // End .comment.
        }
        $out .= html_writer::end_div(); // End .comments.

        return $out;
    }

    /**
     * Render nice times
     *
     * @param integer $time The time to show
     * @return string $out The html output.
     */
    public function render_time($time) {
        $delta = time() - $time;

        // The nice delta.

        // Just show the biggest time unit instead of 2.
        $show = format_time($delta);
        $num = strtok($show, ' ');
        $unit = strtok(' ');
        $show = "$num $unit";
        $show = get_string('ago', 'message', $show);

        // The full date.
        $fulldate = userdate($time, '%d %h %Y %l:%M%P');
        return html_writer::tag('abbr', $show, array('title' => $fulldate) );
    }

    /**
     * Extension attachment file renderer.
     *
     * @param request $req The extension request object.
     * @return string $out The html output.
     */
    public function render_extension_attachments(\local_extension\request $req) {
        list($fs, $files) = $req->fetch_attachments();

        $out  = html_writer::start_tag('div', array('class' => 'attachments'));
        $out .= html_writer::tag('p', get_string('attachments', 'local_extension'));

        foreach ($files as $file) {

            $f = $fs->get_file(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            if (!$f || $f->is_directory()) {
                continue;
            }

            $fileurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            $filelink = html_writer::link($fileurl, $f->get_filename());
            $out .= html_writer::tag('p', $filelink);

        }
        $out .= html_writer::end_div(); // End .attachments.

        if (!empty($req->files)) {
            return $out;
        }
    }

    /**
     * Extension status email renderer.
     *
     * @param request $req The extension request object.
     * @return string $out The html output.
     */
    public function render_extension_email(\local_extension\request $req) {
    }

    /**
     * Render a summary of all requests in a table.
     *
     * @param flexible_table $table
     * @param array $requests
     */
    public function render_extension_summary_table($table, $requests) {

        if (!empty($requests)) {

            foreach ($requests as $request) {
                $statusurl = new moodle_url("/local/extension/status.php", array('id' => $request->id));
                $status = get_string("table_header_statusrow", "local_extension", $statusurl->out());

                $values = array($request->id, $request->count, userdate($request->timestamp), $status);
                $table->add_data($values);

            }
        }

        return $table->finish_output();
    }

    /**
     * Render a summary of all triggers in a table.
     *
     * @param flexible_table $table
     * @param array $triggers
     */
    public function render_extension_trigger_table($table, $triggers) {
        global $OUTPUT;
        if (!empty($triggers)) {

            foreach ($triggers as $id => $trigger) {

                $buttons = array();

                $url = new moodle_url('/local/extension/editrule.php', array_merge(array('id' => $trigger->id, 'datatype' => $trigger->datatype, 'sesskey' => sesskey())));
                $html = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/edit'), 'alt' => get_string('edit'), 'class' => 'iconsmall'));
                $buttons[] = html_writer::link($url, $html, array('title' => get_string('edit')));

                $url = new moodle_url('', array_merge(array('delete' => $trigger->id, 'sesskey' => sesskey())));
                $html = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'), 'alt' => get_string('delete'), 'class' => 'iconsmall'));
                $buttons[] = html_writer::link($url, $html, array('title' => get_string('delete')));

                $parent = null;
                if (!empty($trigger->parent)) {
                    $parent = $triggers["$trigger->parent"]->name;
                }

                // Table columns 'name', 'action', 'role', 'parent', 'continue', 'priority', 'data'.
                $values = array(
                        $trigger->name,
                        $trigger->get_action_name(),
                        $trigger->get_role_name(),
                        $parent,
                        $trigger->datatype,
                        $trigger->priority,
                        var_export($trigger, true),
                        implode(' ', $buttons)
                );

                $table->add_data($values);
            }
        }

        return $table->finish_output();
    }

    /**
     * Adapter trigger renderer for status management page.
     *
     * @param integer $triggerid
     * @return string $html The html output.
     */
    public function render_trigger_item($triggerid) {
        $html  = html_writer::start_tag('div');
        // TODO trigger content.
        $html .= html_writer::tag('p', 'Trigger');
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * TODO
     *
     * @param array $mods
     * @param moodle_url $url
     * @return string $html
     */
    public function render_manage_new_rule($mods, $url) {
        $stredit = get_string('button_edit_rule', 'local_extension');

        $options = array();

        foreach ($mods as $mod) {
            $options[$mod->get_data_type()] = $mod->get_name();
        }

        $html = $this->single_select($url, 'datatype', $options, '', array('' => $stredit), 'newfieldform');

        return $html;
    }
}

