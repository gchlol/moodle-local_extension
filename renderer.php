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
        $fulldate = userdate($time);

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
            /* @var stored_file $file IDE hinting */

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
            $out .= userdate($file->get_timecreated());
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
     * Adapter trigger renderer for status management page.
     *
     * @param \local_extension\rule $rule
     * @param string $parentstr The name of the parent trigger.
     * @return string $html The html output.
     */
    public function render_trigger_rule_text($rule, $parentstr) {
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

        $lengthtype = $rule->rule_type($rule->lengthtype);

        $reqlength = array(
            get_string('form_rule_label_request_length', 'local_extension'),
            $lengthtype,
            $rule->lengthfromduedate,
            get_string('form_rule_label_days_long', 'local_extension'),
        );
        $html .= html_writer::tag('p', implode(' ', $reqlength));

        $elapsedtype = $rule->rule_type($rule->elapsedtype);

        $elapsedlength = array(
            get_string('form_rule_label_elapsed_length', 'local_extension'),
            $elapsedtype,
            $rule->elapsedfromrequest,
            get_string('form_rule_label_days_old', 'local_extension'),
        );
        $html .= html_writer::tag('p', implode(' ', $elapsedlength));

        $setroles = array(
            get_string('form_rule_label_set_roles', 'local_extension'),
            $rule->get_role_name(),
            get_string('form_rule_label_to', 'local_extension'),
            $rule->get_action_name(),
            get_string('form_rule_label_this_request', 'local_extension'),
        );
        $html .= html_writer::tag('p', implode(' ', $setroles));

        $html .= html_writer::end_div();

        return $html;
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

        $inputattributes = array(
            'type' => 'text',
            'id' => 'search',
            'name' => 'search',
            'value' => s($search),
        );

        $formattributes = array(
            'method' => 'get',
            'action' => $url,
            'id' => $id,
            'class' => 'searchform'
        );
        $html  = html_writer::input_hidden_params($url, array('search'));
        $html .= html_writer::tag('input', null, $inputattributes);

        $form = html_writer::tag('form', $html, $formattributes);

        $label = html_writer::span(get_string('renderer_search_text', 'local_extension'));
        $output = $label . html_writer::div($form, 'coursesearch');

        return $output;
    }

    /**
     * Renders the request policy that has been defined in the administration configuration.
     *
     * @return null|string
     */
    public function render_policy() {

        $policy = get_config('local_extension', 'extensionpolicyrequest');

        if (!empty($policy)) {
            return html_writer::div($policy, 'policy');
        }

        return null;
    }

    /**
     * Renders a 'weeks' selection box allowing someone to search for requests in the future.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $searchback
     * @param int $searchforward
     * @return string
     */
    public function render_request_search_controls($courseid, $cmid, $searchback, $searchforward) {
        $maxweeksbackward = get_config('local_extension', 'searchbackwardmaxweeks');
        $maxweeksforward = get_config('local_extension', 'searchforwardmaxweeks');
        $searchforwarddefault = get_config('local_extension', 'searchforward');
        $searchbackwarddefault = get_config('local_extension', 'searchback');

        // Return with a warning message that the look ahead length has exceeded the configured setting.
        if ($searchforward > $maxweeksforward * 7) {
            return html_writer::span(get_string('page_request_outofrange', 'local_extension'));
        }

        // Return with a warning message that the look ahead length has exceeded the configured setting.
        if ($searchback > $maxweeksbackward * 7) {
            return html_writer::span(get_string('page_request_outofrange', 'local_extension'));
        }

        $controlstable = new html_table();
        $controlstable->attributes['class'] = 'controls';
        $controlstable->cellspacing = 0;
        $controlstable->data[] = new html_table_row();

        $popupurl = new moodle_url('/local/extension/request.php', array(
            'course'  => $courseid,
            'cmid'    => $cmid,
            'forward' => $searchforward,
        ));

        // Searching backward
        $backwardlist = array();
        for ($i = 1; $i <= $maxweeksbackward; $i++) {

            // Dont add 'weeks' that are less than the default search length.
            $defaultweeks = $searchbackwarddefault / 7;

            if ($i < $defaultweeks) {
                continue;
            }

            $week  = get_string('week', 'local_extension', $i);
            $weeks = get_string('week_plural', 'local_extension', $i);

            $value = 7 * $i;

            if ($i == 1) {
                $backwardlist[$value] = $week;
            } else {
                $backwardlist[$value] = $weeks;
            }
        }

        $select = new single_select($popupurl, 'back', $backwardlist, $searchback, null, 'requestform');
        $select->set_label(get_string('page_request_searchbackward', 'local_extension'));

        $html = $this->render($select);

        $searchforwardcell = new html_table_cell();
        $searchforwardcell->attributes['class'] = 'right';
        $searchforwardcell->text = $html;

        $controlstable->data[0]->cells[] = $searchforwardcell;

        // Searcahing forward
        $popupurl = new moodle_url('/local/extension/request.php', array(
            'course'  => $courseid,
            'cmid'    => $cmid,
            'back'    => $searchback,
        ));

        $forwardlist = array();

        for ($i = 1; $i <= $maxweeksforward; $i++) {

            // Dont add 'weeks' that are less than the default searchforward length.
            $defaultweeks = $searchforwarddefault / 7;

            if ($i < $defaultweeks) {
                continue;
            }

            $week  = get_string('week', 'local_extension', $i);
            $weeks = get_string('week_plural', 'local_extension', $i);

            $value = 7 * $i;

            if ($i == 1) {
                $forwardlist[$value] = $week;
            } else {
                $forwardlist[$value] = $weeks;
            }
        }

        $select = new single_select($popupurl, 'forward', $forwardlist, $searchforward, null, 'requestform');
        $select->set_label(get_string('page_request_searchforward', 'local_extension'));

        $html = $this->render($select);

        $searchforwardcell = new html_table_cell();
        $searchforwardcell->attributes['class'] = 'right';
        $searchforwardcell->text = $html;

        $controlstable->data[0]->cells[] = $searchforwardcell;

        return html_writer::table($controlstable);

    }

    /**
     * Renders a search item to help assist filtering requests.
     *
     * @param \context $context
     * @param int $categoryid
     * @param int $courseid
     * @param int $stateid
     * @param \moodle_url $baseurl
     * @param string $search
     * @param string $faculty
     * @return string
     */
    public function render_index_search_controls($context, $categoryid, $courseid, $stateid, $baseurl, $search, $faculty) {
        global $SITE;

        $systemcontext = context_system::instance();

        // Print a filter row across the top of the page.
        $controlstable = new html_table();
        $controlstable->attributes['class'] = 'controls';
        $controlstable->cellspacing = 0;
        $controlstable->data[] = new html_table_row();

        $viewallrequests = false;
        $modifyrequeststatus = false;

        if (has_capability('local/extension:viewallrequests', $context)) {
            $viewallrequests = true;
        }

        if (has_capability('local/extension:modifyrequeststatus', $context)) {
            $modifyrequeststatus = true;
        }

        $categorylist = coursecat::make_categories_list('local/extension:viewallrequests');
        // Display a list of categories.
        if (!empty($categorylist) || $viewallrequests || $modifyrequeststatus) {
            $catl = array();
            $catl[0] = coursecat::get(0)->get_formatted_name();
            $catl += $categorylist;

            $popupurl = new moodle_url('/local/extension/index.php');

            $select = new single_select($popupurl, 'catid', $catl, $categoryid, null, 'requestform');

            $strcategories = get_string('page_index_categories', 'local_extension');
            $html  = html_writer::span($strcategories, '', array('id' => 'categories'));
            $html .= $this->render($select);

            $categorycell = new html_table_cell();
            $categorycell->attributes['class'] = 'right';
            $categorycell->text = $html;

            $controlstable->data[0]->cells[] = $categorycell;
        }

        // Obtain the list of courses.
        if (!empty($categoryid)) {
            $courses = coursecat::get($categoryid)->get_courses();
        } else {
            $courses = coursecat::get(0)->get_courses(array('recursive' => true));

        }

        // Display a list of faculties to filter by
        if (!empty($categorylist) || $viewallrequests || $modifyrequeststatus) {
            $options = array();
            $options['0'] = get_string('page_index_all', 'local_extension');

            // TODO add regex as configuration item.
            foreach ($courses as $course) {
                $re = "/^([A-Z]+)[0-9]+_[0-9]+/i";
                if (preg_match($re, $course->shortname, $matches)) {
                    $options[$matches[1]] = $matches[1];
                }

            }

            $popupurl = new moodle_url('/local/extension/index.php', array(
                'catid' => $categoryid
            ));

            $select = new single_select($popupurl, 'faculty', $options, $faculty, null, 'requestform');

            $strcourses = get_string('page_index_faculties', 'local_extension');
            $html = html_writer::span($strcourses, '', array('id' => 'courses'));
            $html .= $this->render($select);

            $categorycell = new html_table_cell();
            $categorycell->attributes['class'] = 'right';
            $categorycell->text = $html;

            $controlstable->data[0]->cells[] = $categorycell;

        }

        // Display a list of all courses to filter by
        // TODO change this to categories that the user is enroled in. / has the cap to modify
        if (!empty($categorylist) || $viewallrequests || $modifyrequeststatus) {
            $options = array();
            $options['1'] = get_string('page_index_all', 'local_extension');

            foreach ($courses as $course) {

                if (!empty($faculty)) {
                    $re = "/^$faculty/i";
                    if (preg_match($re, $course->shortname)) {
                        $options[$course->id] = $course->fullname;
                    }

                } else {
                    $options[$course->id] = $course->fullname;

                }
            }

            $popupurl = new moodle_url('/local/extension/index.php', array(
                'catid' => $categoryid,
                'faculty' => $faculty,
            ));

            $select = new single_select($popupurl, 'id', $options, $courseid, null, 'requestform');

            $strcourses = get_string('page_index_courses', 'local_extension');
            $html = html_writer::span($strcourses, '', array('id' => 'courses'));
            $html .= $this->render($select);

            $categorycell = new html_table_cell();
            $categorycell->attributes['class'] = 'right';
            $categorycell->text = $html;

            $controlstable->data[0]->cells[] = $categorycell;
        }

        // Display a list of enrolled courses to filter by.

        // Or obtain courses that have active requests?

        if ($mycourses = enrol_get_my_courses()) {
            $courselist = array();

            $courselist['1'] = get_string('page_index_all', 'local_extension');

            foreach ($mycourses as $mycourse) {
                $coursecontext = context_course::instance($mycourse->id);
                $courselist[$mycourse->id] = format_string($mycourse->fullname, true, array('context' => $coursecontext));
            }

            if (has_capability('moodle/site:viewparticipants', $systemcontext)) {
                unset($courselist[SITEID]);
                $obj = array('context' => $systemcontext);
                $courselist = array(SITEID => format_string($SITE->fullname, true, $obj)) + $courselist;
            }

            if (array_key_exists($courseid, $mycourses)) {
                $categoryid = $mycourses[$courseid]->category;
            }

            $popupurl = new moodle_url('/local/extension/index.php', array(
                'catid' => $categoryid,
            ));

            $select = new single_select($popupurl, 'id', $courselist, $courseid, null, 'requestform');

            $html  = html_writer::span(get_string('mycourses'), '', array('id' => 'courses'));
            $html .= $this->render($select);
            $controlstable->data[0]->cells[] = $html;
        }

        if (!empty($categorylist) || $viewallrequests || $modifyrequeststatus) {

            // Display a search filter for the status.
            $state = \local_extension\state::instance();

            $statelist = array();
            $statelist[0] = get_string('page_index_all', 'local_extension');
            foreach ($state->statearray as $sid => $name) {
                $statelist[$sid] = $state->get_state_name($sid);
            }

            $popupurl = new moodle_url('/local/extension/index.php', array(
                'catid' => $categoryid,
                'id' => $courseid,
                'faculty' => $faculty,
            ));

            $select = new single_select($popupurl, 'state', $statelist, $stateid, null, 'requestform');

            $html  = html_writer::span(get_string('state', 'local_extension'), '', array('id' => 'courses'));
            $html .= $this->render($select);
            $controlstable->data[0]->cells[] = $html;

        }

        $searchcoursecell = new html_table_cell();
        $searchcoursecell->attributes['class'] = 'right';
        $searchcoursecell->text = $this->render_search_course($baseurl, 'id', $search);
        $controlstable->data[0]->cells[] = $searchcoursecell;

        return html_writer::table($controlstable);
    }

}
