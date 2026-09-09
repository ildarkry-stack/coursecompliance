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
 * Classes for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\form;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to start a compliance scan.
 */
final class scan_form extends \moodleform {
    /**
     * Define the form fields.
     */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $categories = \core_course_category::make_categories_list();
        $profiles = $DB->get_records_menu('tool_coursecompliance_profile', ['enabled' => 1], 'name ASC', 'id,name');
        $mform->addElement('select', 'categoryid', get_string('category', 'tool_coursecompliance'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');
        $mform->addElement('select', 'profileid', get_string('profile', 'tool_coursecompliance'), $profiles);
        $mform->setType('profileid', PARAM_INT);
        $mform->addRule('profileid', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('startscan', 'tool_coursecompliance'));
    }
}
