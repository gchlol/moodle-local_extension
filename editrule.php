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
 * Edit a rule / trigger.
 *
 * @package    local_extension
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$triggerid = optional_param('id', 0, PARAM_INT);
$datatype = optional_param('datatype', '', PARAM_ALPHANUM);

$PAGE->set_url(new moodle_url('/local/extension/editrule.php'));

$context = \context_system::instance();
require_login();

\admin_externalpage_setup('local_extension_settings_rules');

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('rules_page_heading', 'local_extension'));
$PAGE->requires->css('/local/extension/styles.css');

$renderer = $PAGE->get_renderer('local_extension');

$data = null;
if (!empty($triggerid) && confirm_sesskey()) {
    $record = $DB->get_record('local_extension_triggers', array('id' => $triggerid), '*', MUST_EXIST);
    $data = \local_extension\rule::from_db($record);
    $data->datatype = $datatype;

    // Set the saved serialised data as object properties, which will be loaded as default form values.
    if (!empty($data->data)) {
        foreach ($data->data as $key => $value) {
            $data->$key = $value;
        }
    }
}

$parents = $DB->get_records_menu('local_extension_triggers', null, 'id ASC', 'id, name');

$mform = new \local_extension\form\rule(null, array('parents' => $parents, 'datatype' => $datatype));
$mform->set_data($data);

if ($mform->is_cancelled()) {

    $url = new moodle_url('/local/extension/manage.php');
    redirect($url);
    die;

} else if ($form = $mform->get_data()) {

    $rule = new \local_extension\rule();
    $rule->load_from_form($form);

    if (!empty($rule->id)) {
        $DB->update_record('local_extension_triggers', $rule);
    } else {
        $DB->insert_record('local_extension_triggers', $rule);
    }

    $url = new moodle_url('/local/extension/manage.php');
    redirect($url);
    die;

}

echo $OUTPUT->header();
echo $mform->display();
echo $OUTPUT->footer();
