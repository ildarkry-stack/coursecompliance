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
 * Course Compliance settings form.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Settings form rendered inside the plugin interface.
 */
final class plugin_settings_form extends \moodleform {
    /**
     * Define fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'text',
            'retentiondays',
            get_string('retentiondays', 'tool_coursecompliance'),
            ['size' => 8]
        );
        $mform->setType('retentiondays', PARAM_INT);
        $mform->addHelpButton('retentiondays', 'retentiondays', 'tool_coursecompliance');

        $mform->addElement(
            'select',
            'scanbatchsize',
            get_string('scanbatchsize', 'tool_coursecompliance'),
            [5 => '5', 10 => '10', 20 => '20', 30 => '30', 50 => '50']
        );
        $mform->addHelpButton('scanbatchsize', 'scanbatchsize', 'tool_coursecompliance');

        $categoryoptions = [0 => get_string('notselected', 'tool_coursecompliance')]
            + \core_course_category::make_categories_list();
        $mform->addElement(
            'select',
            'sessiondefaultcategory',
            get_string('sessiondefaultcategory', 'tool_coursecompliance'),
            $categoryoptions
        );
        $mform->addHelpButton(
            'sessiondefaultcategory',
            'sessiondefaultcategory',
            'tool_coursecompliance'
        );

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Validate settings.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)($data['retentiondays'] ?? 0) < 1) {
            $errors['retentiondays'] = get_string('positivevalueexpected', 'tool_coursecompliance');
        }
        return $errors;
    }
}
