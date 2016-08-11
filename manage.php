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
 * Manage the adapter triggers
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$delete   = optional_param('delete', 0, PARAM_INT);
$confirm  = optional_param('confirm', '', PARAM_ALPHANUM);   // MD5 confirmation hash.

$pageurl = new moodle_url('/local/extension/manage.php');
$PAGE->set_url($pageurl);

$context = \context_system::instance();
require_login();

\admin_externalpage_setup('local_extension_settings_rules');

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('rules_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$renderer = $PAGE->get_renderer('local_extension');

if ($delete && confirm_sesskey()) {

    if ($confirm != md5($delete)) {
        $query = "SELECT id, name
                    FROM {local_extension_triggers}
                   WHERE id = ?
                      OR parent = ?";

        $params = array('id' => $delete, 'parent' => $delete);

        $result = $DB->get_records_sql($query, $params);

        echo $OUTPUT->header();
        echo html_writer::tag('h2', get_string('page_heading_manage_delete', 'local_extension'));

        $optionsyes = array('delete' => $delete, 'confirm' => md5($delete), 'sesskey' => sesskey());
        $deleteurl = new moodle_url($pageurl, $optionsyes);
        $deletebutton = new single_button($deleteurl, get_string('delete'), 'post');

        echo $renderer->render_delete_rules($result);

        echo $OUTPUT->confirm('', $deletebutton, $pageurl);
        echo $OUTPUT->footer();

        exit();

    } else if (data_submitted()) {

        // Select all child rules for id.
        $sql = "SELECT id
                  FROM {local_extension_triggers}
                 WHERE parent = ?";
        $params = array($delete);

        // List of rule ids associated to this $delete id.
        $items = $DB->get_fieldset_sql($sql, $params);

        // Add the $ruleid.
        $items[] = $delete;

        // Remove all rules, including the children.
        $DB->delete_records_list('local_extension_triggers', 'id', $items);
        redirect($pageurl);

    } else {

        redirect($pageurl);
    }
}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('page_heading_manage', 'local_extension'));

// Display a table of all triggers when no id is present.
$table = \local_extension\table::generate_trigger_table();
$data = \local_extension\table::generate_trigger_data($table);
echo $renderer->render_extension_trigger_table($table, $data);

echo html_writer::empty_tag('br');

$url = new moodle_url("/local/extension/editrule.php");

$mods = \local_extension\plugininfo\extension::get_enabled_request();

echo $renderer->render_manage_new_rule($mods, $url);

echo $OUTPUT->footer();
