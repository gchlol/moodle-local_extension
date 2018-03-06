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
 * @package     local_extension
 * @author      Daniel Thee Roperto <daniel.roperto@catalyst-au.net>
 * @copyright   2018 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extension\form;

use local_extension\preferences_manager;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/formslib.php");

class preferences_form extends moodleform {
    public function definition() {
        $this->_form->addElement('checkbox', preferences_manager::MAIL_DIGEST,
                                 get_string('preference_mail_digest', 'local_extension'),
                                 get_string('preference_mail_digest_help', 'local_extension'));
        $this->_form->setDefault('mail_digest', preferences_manager::get(preferences_manager::MAIL_DIGEST));

        $this->add_action_buttons();
    }
}
