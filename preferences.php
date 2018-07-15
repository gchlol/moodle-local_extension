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
 * User preferences page.
 *
 * @package     local_rollover
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_extension\form\preferences_form;
use local_extension\preferences;

require_once(__DIR__ . '/../../config.php');

require_login(null, false);

$PAGE->set_url('/local/extension/preferences.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_extension'));
$PAGE->set_heading(get_string('page_heading_index', 'local_extension'));
$PAGE->add_body_class('local_extension');

$PAGE->navbar->add(get_string('breadcrumb_nav_index', 'local_extension'),
                   new moodle_url('/local/extension/index.php'));
$PAGE->navbar->add(get_string('breadcrumb_nav_preferences', 'local_extension'),
                   new moodle_url('/local/extension/preferences.php'));

$form = new preferences_form();
if ($form->is_cancelled()) {
    redirect('/local/extension/index.php');
}

// Save the preferences.
$data = $form->get_data();
if (!is_null($data)) {
    $preferences = new preferences();
    $preferences->set(preferences::MAIL_DIGEST, !empty($data->{preferences::MAIL_DIGEST}));
    $preferences->set(preferences::EXPORT_CSV, !empty($data->{preferences::EXPORT_CSV}));
    redirect("$CFG->wwwroot/local/extension", get_string('preferences_saved', 'local_extension'));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('preferences_title', 'local_extension'));
$form->display();

echo $OUTPUT->footer();
