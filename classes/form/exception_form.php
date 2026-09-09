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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating an exception from an audit result.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exception_form extends \moodleform {
    /**
     * Define form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'resultid');
        $mform->setType('resultid', PARAM_INT);
        $mform->addElement(
            'textarea',
            'reason',
            get_string('exceptionreason', 'tool_coursecompliance'),
            ['rows' => 4, 'cols' => 70]
        );
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');
        $mform->addElement(
            'date_time_selector',
            'expiresat',
            get_string('exceptionexpires', 'tool_coursecompliance'),
            ['optional' => true]
        );
        $this->add_action_buttons(true, get_string('createexception', 'tool_coursecompliance'));
    }
}
