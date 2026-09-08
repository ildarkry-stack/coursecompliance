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

/**
 * Form for planning exam and resit windows across a course category.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_schedule_form extends \moodleform {
    /** @inheritdoc */
    protected function definition(): void {
        $mform = $this->_form;
        $categories = \core_course_category::make_categories_list('tool/coursecompliance:scheduleassessments');

        $mform->addElement('autocomplete', 'categoryid', get_string('category', 'tool_coursecompliance'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');
        $defaultcategory = (int)get_config('tool_coursecompliance', 'sessiondefaultcategory');
        if (!$defaultcategory) {
            $defaultcategory = (int)get_config('local_examdates', 'default_category');
        }
        if ($defaultcategory && isset($categories[$defaultcategory])) {
            $mform->setDefault('categoryid', $defaultcategory);
        }

        $mform->addElement('advcheckbox', 'includesubcategories', get_string('includesubcategories', 'tool_coursecompliance'));
        $mform->setType('includesubcategories', PARAM_INT);
        $mform->setDefault('includesubcategories', 1);

        $mform->addElement('advcheckbox', 'adjustassignmentdates', get_string('adjustassignmentdates', 'tool_coursecompliance'));
        $mform->setType('adjustassignmentdates', PARAM_INT);
        $mform->setDefault('adjustassignmentdates', 1);
        $mform->addHelpButton('adjustassignmentdates', 'adjustassignmentdates', 'tool_coursecompliance');

        $this->add_period('exam', 1);
        $this->add_period('resit1', 0);
        $this->add_period('resit2', 0);

        $this->add_action_buttons(false, get_string('previewchanges', 'tool_coursecompliance'));
    }

    /**
     * Add one assessment period block.
     *
     * @param string $period exam|resit1|resit2.
     * @param int $checked Default checkbox state.
     */
    private function add_period(string $period, int $checked): void {
        $mform = $this->_form;
        $mform->addElement('header', $period . 'header', get_string('period_' . $period, 'tool_coursecompliance'));

        $mform->addElement('advcheckbox', 'update_' . $period, get_string('scheduleperiod', 'tool_coursecompliance'));
        $mform->setType('update_' . $period, PARAM_INT);
        $mform->setDefault('update_' . $period, $checked);

        $mform->addElement('text', $period . '_quizidnumber', get_string('quizidnumber', 'tool_coursecompliance'));
        $mform->setType($period . '_quizidnumber', PARAM_ALPHANUMEXT);
        $mform->setDefault($period . '_quizidnumber', $period);
        $mform->disabledIf($period . '_quizidnumber', 'update_' . $period, 'notchecked');

        $mform->addElement('text', $period . '_assignidnumber', get_string('assignidnumber', 'tool_coursecompliance'));
        $mform->setType($period . '_assignidnumber', PARAM_ALPHANUMEXT);
        $mform->setDefault($period . '_assignidnumber', '');
        $mform->disabledIf($period . '_assignidnumber', 'update_' . $period, 'notchecked');

        $mform->addElement('date_time_selector', $period . '_open', get_string('periodopen', 'tool_coursecompliance'));
        $mform->disabledIf($period . '_open', 'update_' . $period, 'notchecked');
        $mform->setDefault($period . '_open', $this->default_time(0, 1));

        $mform->addElement('date_time_selector', $period . '_close', get_string('periodclose', 'tool_coursecompliance'));
        $mform->disabledIf($period . '_close', 'update_' . $period, 'notchecked');
        $mform->setDefault($period . '_close', $this->default_time(23, 59));
    }

    /**
     * Default timestamp in the user's timezone.
     *
     * @param int $hour Hour.
     * @param int $minute Minute.
     * @return int
     */
    private function default_time(int $hour, int $minute): int {
        $parts = usergetdate(time());
        return make_timestamp($parts['year'], $parts['mon'], $parts['mday'], $hour, $minute);
    }

    /**
     * Validate session planning input.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = [];
        $selected = false;
        foreach (['exam', 'resit1', 'resit2'] as $period) {
            if (empty($data['update_' . $period])) {
                continue;
            }
            $selected = true;
            $quizidnumber = trim((string)($data[$period . '_quizidnumber'] ?? ''));
            $assignidnumber = trim((string)($data[$period . '_assignidnumber'] ?? ''));
            if ($quizidnumber === '' && $assignidnumber === '') {
                $errors[$period . '_quizidnumber'] = get_string('sessiontargetrequired', 'tool_coursecompliance');
            }
            if ((int)$data[$period . '_close'] <= (int)$data[$period . '_open']) {
                $errors[$period . '_close'] = get_string('invalidperioddates', 'tool_coursecompliance');
            }
        }
        if (!$selected) {
            $errors['categoryid'] = get_string('selectperiodrequired', 'tool_coursecompliance');
        }
        foreach (['quiz', 'assign'] as $modname) {
            $seen = [];
            foreach (['exam', 'resit1', 'resit2'] as $period) {
                if (empty($data['update_' . $period])) {
                    continue;
                }
                $field = $period . '_' . $modname . 'idnumber';
                $idnumber = trim((string)($data[$field] ?? ''));
                if ($idnumber === '') {
                    continue;
                }
                if (isset($seen[$idnumber])) {
                    $errors[$field] = get_string('duplicatesessiontargetconfig', 'tool_coursecompliance', $idnumber);
                }
                $seen[$idnumber] = $period;
            }
        }
        return $errors;
    }
}
