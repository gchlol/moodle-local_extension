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
     * @param request $req The extension comment object.
     * @return string $out The html output.
     */
    public function render_extension_status(\local_extension\request $req) {
        $out  = $this->render_extension_comments($req);
        $out .= $this->render_extension_attachments($req);

        return $out;
    }

    /**
     * Extension comment renderer.
     *
     * @param request $req The extension comment object.
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
            $out .= html_writer::tag('div', fullname($user), array('class' => 'name'));
            $out .= html_writer::start_tag('div', array('class' => 'message'));
            $out .= html_writer::div($comment->message, 'comment'); // TODO proper escape.
            $out .= html_writer::end_div(); // End .message.
            $out .= html_writer::end_div(); // End .content.
            $out .= html_writer::end_div(); // End .comment.
        }
        $out .= html_writer::end_div();

        return $out;
    }

    /**
     * Extension attached file renderer.
     *
     * @param request $req The extension comment object.
     * @return string $out The html output.
     */
    public function render_extension_attachments(\local_extension\request $req) {
        $fs = get_file_storage();

        $out = '';

        foreach ($req->files as $file) {
            $path = '/' .
                    $file->get_contextid() .
                    '/' .
                    $file->get_component() .
                    '/' .
                    $file->get_filearea() .
                    '/' .
                    $file->get_itemid() .
                    $file->get_filepath() .
                    $file->get_filename();

            if (!($file = $fs->get_file_by_hash(sha1($path))) || $file->is_directory()) {
                continue;
            }

            var_dump($file);

            $fileurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            $out .= html_writer::start_tag('div');
            $out .= html_writer::link($fileurl, $file->get_filename());
            $out .= html_writer::end_div();
        }

        return $out;
    }

}