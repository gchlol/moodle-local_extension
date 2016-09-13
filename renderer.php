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
     * @param boolean $showdate If this is set, then print the full date instead of 'time ago'.
     * @return string $out The html output.
     */
    public function render_extension_comments(\local_extension\request $req, $showdate = false) {
        $out = '';

        $out .= html_writer::start_tag('div', array('class' => 'comments'));

        // Fetch the comments, state changes and file attachments.
        $comments = $req->get_history();

        foreach ($comments as $comment) {
            $out .= $this->render_single_comment($req, $comment, $showdate);
        }

        $out .= html_writer::end_div(); // End .comments.

        return $out;
    }

    /**
     * Helper function to render a single comment. Also used in email notifications.
     *
     * @param \local_extension\request $req
     * @param stdClass $comment
     * @param boolean $showdate If this is set, then print the full date instead of 'time ago'.
     * @return string $out
     */
    public function render_single_comment(\local_extension\request $req, $comment, $showdate = false) {
        $class = 'content';
        $out = '';

        // Add a css class to change background color for file attachments and state changes.
        if (!empty($comment->filehash)) {
            $class .= ' fileattachment';
        }

        if (!empty($comment->state)) {
            $class .= ' statechange';
        }

        if (array_key_exists($comment->userid, $req->users)) {
            $user = $req->users[$comment->userid];
        } else {
            $user = \core_user::get_user($comment->userid);
        }

        $out .= html_writer::start_tag('div', array('class' => 'comment'));

        $out .= html_writer::start_tag('div', array('class' => 'avatar'));
        $out .= $this->output->user_picture($user, array(
            'size' => 50,
        ));
        $out .= html_writer::end_div(); // End .avatar.

        $out .= html_writer::start_tag('div', array('class' => $class));
        $out .= html_writer::tag('span', fullname($user), array('class' => 'name'));

        $out .= html_writer::tag('span', ' - ' . $this->render_role($req, $user->id), array('class' => 'role'));
        $out .= html_writer::tag('span', ' - ' . $this->render_time($comment->timestamp, $showdate), array('class' => 'time'));

        $out .= html_writer::start_tag('div', array('class' => 'message'));
        $out .= html_writer::div(format_text(trim($comment->message), FORMAT_MOODLE), 'comment');
        $out .= html_writer::end_div(); // End .message.
        $out .= html_writer::end_div(); // End .content.
        $out .= html_writer::end_div(); // End .comment.

        return $out;
    }

    /**
     * Renders role information
     *
     * @param \local_extension\request $req
     * @param integer $userid
     * @return string The html output.
     */
    public function render_role($req, $userid) {
        $details = '';
        $rolename = '';
        $roles = array();

        // Roles are scoped to the enrollment status in courses.
        foreach ($req->mods as $cmid => $mod) {
            $course = $mod['course'];
            $context = \context_course::instance($course->id);
            $roles = get_user_roles($context, $userid, true);

            foreach ($roles as $role) {
                $rolename = role_get_name($role, $context);
                if (!empty($rolename)) {
                    $details .= "{$rolename} - {$course->fullname}\n";
                }
            }
        }

        return html_writer::tag('abbr', $rolename, array('title' => $details) );

    }

    /**
     * Render nice times
     *
     * @param integer $time The time to show
     * @param boolean $showdate If this is set, then print the full date instead of 'time ago'.
     * @return string The html output.
     */
    public function render_time($time, $showdate = false) {
        $delta = time() - $time;

        // The nice delta.

        // Just show the biggest time unit instead of 2.
        $show = format_time($delta);
        $num = strtok($show, ' ');
        $unit = strtok(' ');
        $show = "$num $unit";

        // The full date.
        $fulldate = userdate($time, '%d %h %Y %l:%M%P');

        if ($showdate) {
            return html_writer::tag('abbr', $fulldate);
        } else {
            return html_writer::tag('abbr', $show, array('title' => $fulldate));
        }

    }

    /**
     * Extension attachment file renderer.
     *
     * @param \local_extension\request $req The extension request object.
     * @return string $out The html output.
     */
    public function render_extension_attachments(\local_extension\request $req) {
        global $OUTPUT;

        list($fs, $files) = $req->fetch_attachments();

        $out  = html_writer::start_tag('div', array('class' => 'attachments'));
        $out .= html_writer::tag('p', get_string('attachments', 'local_extension'));
        $out .= html_writer::start_tag('ul');

        foreach ($files as $file) {
            /* @var stored_file $file */

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

            $out .= html_writer::start_tag('li', array('class' => 'attachment'));
            $out .= $OUTPUT->pix_icon(file_file_icon($file, 16), get_mimetype_description($file)) . ' ';
            $out .= html_writer::link($fileurl, $f->get_filename()) . "  ";
            $out .= userdate($file->get_timecreated(), '%d %h %Y %l:%M%P');
            $out .= html_writer::end_tag('li'); // End .attachment.

        }

        $out .= html_writer::end_tag('ul'); // End .attachment.
        $out .= html_writer::end_div(); // End .attachments.

        // The first file will be '.'
        if (count($files) > 1) {
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

                $values = array(
                    $request->id,
                    $request->count,
                    userdate($request->timestamp),
                    userdate($request->lastmod),
                    $status,
                    \fullname(\core_user::get_user($request->userid)),
                );
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
     * @param integer $parent
     */
    public function render_extension_trigger_table($table, $triggers, $parent = null) {
        global $OUTPUT;
        if (!empty($triggers)) {

            foreach ($triggers as $id => $trigger) {

                if (empty($trigger->parent)) {

                    // Adding an empty row to divide sets of rules.
                    $empty = array(
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                    );

                    $table->add_data($empty, 'clearrow');
                }

                $buttons = array();

                $url = new moodle_url('/local/extension/editrule.php', array_merge(array('id' => $trigger->id, 'datatype' => $trigger->datatype, 'sesskey' => sesskey())));
                $html = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/edit'), 'alt' => get_string('edit'), 'class' => 'iconsmall'));
                $buttons[] = html_writer::link($url, $html, array('title' => get_string('edit')));

                $url = new moodle_url('', array_merge(array('delete' => $trigger->id, 'sesskey' => sesskey())));
                $html = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'), 'alt' => get_string('delete'), 'class' => 'iconsmall'));
                $buttons[] = html_writer::link($url, $html, array('title' => get_string('delete')));

                $parentstr = null;
                if (!empty($parent)) {
                    $parentstr = $parent->name;
                }

                // Table columns 'name', 'action', 'role', 'parent', 'type', 'priority', 'rules'.
                $values = array(
                    $trigger->name,
                    $trigger->get_action_name(),
                    $trigger->get_role_name(),
                    $parentstr,
                    $trigger->datatype,
                    $trigger->priority + 1,
                    $this->render_trigger_rule_text($trigger, $parentstr),
                    implode(' ', $buttons)
                );

                $table->add_data($values);

                // Render the child items.
                if (!empty($trigger->children)) {
                    $this->render_extension_trigger_table($table, $trigger->children, $trigger);
                }
            }
        }

        return $table;
    }

    /**
     * Adapter trigger renderer for status management page.
     *
     * @param \local_extension\rule $trigger
     * @param string $parentstr The name of the parent trigger.
     * @return string $html The html output.
     */
    public function render_trigger_rule_text($trigger, $parentstr) {
           $html  = html_writer::start_tag('div');

        if (empty($parentstr)) {
            $activate = get_string('form_rule_label_parent_allways', 'local_extension');
            $html .= html_writer::tag('p', $activate);
        } else {
            $activate = array(
                get_string('form_rule_label_parent', 'local_extension'),
                $parentstr,
                get_string('form_rule_label_parent_end', 'local_extension'),
            );
            $html .= html_writer::tag('p', implode(' ', $activate));
        }

        $lengthtype = $this->rule_type($trigger->lengthtype);

        $reqlength = array(
            get_string('form_rule_label_request_length', 'local_extension'),
            $lengthtype,
            $trigger->lengthfromduedate,
            get_string('form_rule_label_days_long', 'local_extension'),
        );
        $html .= html_writer::tag('p', implode(' ', $reqlength));

        $elapsedtype = $this->rule_type($trigger->elapsedtype);

        $elapsedlength = array(
            get_string('form_rule_label_elapsed_length', 'local_extension'),
            $elapsedtype,
            $trigger->elapsedfromrequest,
            get_string('form_rule_label_days_old', 'local_extension'),
        );
        $html .= html_writer::tag('p', implode(' ', $elapsedlength));

        $setroles = array(
            get_string('form_rule_label_set_roles', 'local_extension'),
            $trigger->get_role_name(),
            get_string('form_rule_label_to', 'local_extension'),
            $trigger->get_action_name(),
            get_string('form_rule_label_this_request', 'local_extension'),
        );
        $html .= html_writer::tag('p', implode(' ', $setroles));

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Internal helper function to return the type of rule length checking.
     * @param string $triggertype
     * @return string
     */
    private function rule_type($triggertype) {
        $greaterthan = get_string('form_rule_greater_or_equal', 'local_extension');
        $lessthan = get_string('form_rule_less_than', 'local_extension');
        $any = get_string('form_rule_any_value', 'local_extension');

        $type = '';

        switch($triggertype) {
            case \local_extension\rule::RULE_CONDITION_GE:
                $type = $greaterthan;
                break;
            case \local_extension\rule::RULE_CONDITION_LT:
                $type = $lessthan;
                break;
            case \local_extension\rule::RULE_CONDITION_ANY:
                $type = $any;
                break;
            default:
                $type = '';
                break;
        }

        return $type;
    }

    /**
     * Renders a dropdown select box with the available rule type handlers.
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

    /**
     * Prints the list of rules, and child rules that may be deleted on manage.php
     *
     * @param \local_extension\rule $branch
     * @param string $html
     * @return string
     */
    public function render_delete_rules($branch, $html = null) {
        if (empty($html)) {
            $html = '';
        }

        foreach ($branch as $rule) {
            if (!empty($rule->parentrule->name)) {
                $parentstr = $rule->parentrule->name;
            } else {
                $parentstr = '';
            }

            $html .= html_writer::start_div('manageruleitem');
            $html .= $this->render_trigger_rule_text($rule, $parentstr);
            $html .= html_writer::end_div();
            $html .= html_writer::empty_tag('br');

            if (!empty($rule->children)) {
                $html = $this->render_delete_rules($rule->children, $html);
            }
        }

        return $html;
    }

    /**
     * Renders a search input form element that is used with filtering the requests based on course.
     *
     * @param \moodle_url $url
     * @param string $name
     * @param string $search
     * @return string
     */
    public function render_search_course($url, $name, $search) {
        $id = html_writer::random_id('search_course_f');

        $label = html_writer::span('Search Courses');

        $inputattributes = array(
            'type' => 'text',
            'id' => 'search',
            'name' => 'search',
            'value' => s($search),
        );

        $html = html_writer::input_hidden_params($url, array('search'));

        $html .= html_writer::tag('input', null, $inputattributes);

        $formattributes = array(
            'method' => 'get',
            'action' => $url,
            'id' => $id,
            'class' => 'searchform'
        );

        $form = html_writer::tag('form', $html, $formattributes);

        $output = $label . html_writer::div($form, 'coursesearch');

        return $output;
    }

}

