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
        $profiles = [0 => get_string('notselected', 'tool_coursecompliance')] + $profiles;
        $selectedcategory = (int)($this->_customdata['categoryid'] ?? 0);
        $selectedprofile = (int)($this->_customdata['profileid'] ?? 0);

        $mform->addElement('select', 'profileid', get_string('profile', 'tool_coursecompliance'), $profiles);
        $mform->setType('profileid', PARAM_INT);
        $mform->setDefault('profileid', $selectedprofile);

        $mform->addElement('select', 'categoryid', get_string('category', 'tool_coursecompliance'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        if ($selectedcategory > 0) {
            $mform->setDefault('categoryid', $selectedcategory);
        }
        $mform->addElement('submit', 'loadcourses', get_string('loadcourses', 'tool_coursecompliance'));
        $mform->registerNoSubmitButton('loadcourses');

        $mform->addElement('hidden', 'courseidsjson', '');
        $mform->setType('courseidsjson', PARAM_TEXT);
        $mform->addElement('static', 'selectedcoursesvalidation', '', '');

        if ($selectedcategory > 0) {
            $category = \core_course_category::get($selectedcategory, MUST_EXIST, true);
            $categoryids = array_merge([$selectedcategory], $category->get_all_children_ids());
            [$categorysql, $categoryparams] = $DB->get_in_or_equal(
                $categoryids,
                SQL_PARAMS_NAMED,
                'pickcategory'
            );
            $courses = $DB->get_records_select(
                'course',
                "category $categorysql AND id <> :siteid",
                $categoryparams + ['siteid' => SITEID],
                'fullname ASC, id ASC',
                'id,fullname,shortname,category'
            );
            if ($courses) {
                $states = \tool_coursecompliance\scan_analysis::latest_course_states(
                    array_keys($courses),
                    $selectedprofile
                );
                $categoryrecords = $DB->get_records_list(
                    'course_categories',
                    'id',
                    $categoryids,
                    '',
                    'id,name'
                );
                $mform->addElement(
                    'html',
                    self::course_picker_html($courses, $states, $categoryrecords)
                );
            } else {
                $mform->addElement(
                    'static',
                    'nocourses',
                    '',
                    get_string('nocoursesincategory', 'tool_coursecompliance')
                );
            }
        } else {
            $mform->addElement(
                'static',
                'coursepickerintro',
                '',
                get_string('coursepickerintro', 'tool_coursecompliance')
            );
        }

        $this->add_action_buttons(false, get_string('startscan', 'tool_coursecompliance'));
    }

    /**
     * Validate submitted scan parameters.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);
        $profileid = (int)($data['profileid'] ?? 0);
        $categoryid = (int)($data['categoryid'] ?? 0);
        $profileexists = $profileid > 0 && $DB->record_exists(
            'tool_coursecompliance_profile',
            ['id' => $profileid, 'enabled' => 1]
        );
        if (!$profileexists) {
            $errors['profileid'] = get_string('profilemustbeselected', 'tool_coursecompliance');
        }
        if ($categoryid <= 0 || !$DB->record_exists('course_categories', ['id' => $categoryid])) {
            $errors['categoryid'] = get_string('required');
        }

        $courseids = self::parse_course_ids((string)($data['courseidsjson'] ?? ''));
        if (!$courseids) {
            $errors['selectedcoursesvalidation'] = get_string('selectatleastonecourse', 'tool_coursecompliance');
        } else if ($categoryid > 0) {
            $category = \core_course_category::get($categoryid, MUST_EXIST, true);
            $categoryids = array_merge([$categoryid], $category->get_all_children_ids());
            [$courseinsql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'selectedcourse');
            [$categorysql, $categoryparams] = $DB->get_in_or_equal(
                $categoryids,
                SQL_PARAMS_NAMED,
                'selectedcategory'
            );
            $params += $categoryparams;
            $params['siteid'] = SITEID;
            $validcount = $DB->count_records_select(
                'course',
                "id $courseinsql AND category $categorysql AND id <> :siteid",
                $params
            );
            if ($validcount !== count($courseids)) {
                $errors['selectedcoursesvalidation'] = get_string('invalidcourseselection', 'tool_coursecompliance');
            }
        }
        return $errors;
    }

    /**
     * Parse the hidden selected-course value.
     *
     * @param string $value Comma-separated course ids.
     * @return int[]
     */
    public static function parse_course_ids(string $value): array {
        if (trim($value) === '') {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            'intval',
            preg_split('/\\s*,\\s*/', trim($value)) ?: []
        ))));
    }

    /**
     * Build the visual course selector.
     *
     * @param array $courses Courses in the category tree.
     * @param array $states Latest readiness snapshots.
     * @param array $categories Category records keyed by id.
     * @return string
     */
    private static function course_picker_html(array $courses, array $states, array $categories): string {
        $html = \html_writer::start_div('cc-course-picker mt-3', ['data-region' => 'cc-course-picker']);
        $html .= \html_writer::div(
            get_string('coursepickerhelp', 'tool_coursecompliance'),
            'alert alert-info py-2'
        );
        $html .= \html_writer::start_div('d-flex flex-wrap gap-2 align-items-center mb-3');
        $html .= \html_writer::empty_tag('input', [
            'type' => 'search',
            'class' => 'form-control cc-course-picker-search',
            'placeholder' => get_string('searchcourseplaceholder', 'tool_coursecompliance'),
            'data-action' => 'search',
        ]);
        $html .= \html_writer::tag(
            'button',
            get_string('selectallcourses', 'tool_coursecompliance'),
            ['type' => 'button', 'class' => 'btn btn-sm btn-outline-primary', 'data-action' => 'select-all']
        );
        $html .= \html_writer::tag(
            'button',
            get_string('clearselection', 'tool_coursecompliance'),
            ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary', 'data-action' => 'clear']
        );
        foreach ([5, 10, 20] as $count) {
            $html .= \html_writer::tag(
                'button',
                get_string('selectfirstcourses', 'tool_coursecompliance', $count),
                [
                    'type' => 'button',
                    'class' => 'btn btn-sm btn-outline-secondary',
                    'data-action' => 'select-first',
                    'data-count' => $count,
                ]
            );
        }
        $html .= \html_writer::span(
            get_string('selectedcoursescount', 'tool_coursecompliance', 0),
            'ms-auto small text-muted',
            ['data-region' => 'selected-count']
        );
        $html .= \html_writer::end_div();

        $html .= \html_writer::start_div('table-responsive');
        $html .= \html_writer::start_tag('table', ['class' => 'table table-hover align-middle cc-course-picker-table']);
        $html .= \html_writer::start_tag('thead');
        $html .= \html_writer::tag('tr',
            \html_writer::tag('th', '', ['class' => 'cc-course-picker-check']) .
            \html_writer::tag('th', get_string('course')) .
            \html_writer::tag('th', get_string('lastcheck', 'tool_coursecompliance')) .
            \html_writer::tag('th', get_string('coursecondition', 'tool_coursecompliance'))
        );
        $html .= \html_writer::end_tag('thead');
        $html .= \html_writer::start_tag('tbody');
        foreach ($courses as $course) {
            $state = $states[(int)$course->id] ?? null;
            $status = get_string('neverchecked', 'tool_coursecompliance');
            $statusclass = 'bg-secondary';
            $date = '—';
            $details = '';
            if ($state) {
                $date = userdate((int)$state->scandate);
                if ((string)$state->status === 'ready') {
                    $status = get_string('ready', 'tool_coursecompliance');
                    $statusclass = 'bg-success';
                } else {
                    $status = get_string('notready', 'tool_coursecompliance');
                    $statusclass = 'bg-danger';
                }
                $details = get_string('courseissuescompact', 'tool_coursecompliance', (object)[
                    'critical' => (int)$state->critical,
                    'warnings' => (int)$state->warnings,
                    'score' => (int)$state->score,
                ]);
            }
            $searchtext = \core_text::strtolower($course->fullname . ' ' . $course->shortname);
            $html .= \html_writer::start_tag('tr', [
                'data-courseid' => (int)$course->id,
                'data-search' => $searchtext,
            ]);
            $html .= \html_writer::tag(
                'td',
                \html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'class' => 'form-check-input',
                    'data-role' => 'course-checkbox',
                    'value' => (int)$course->id,
                    'aria-label' => get_string('selectcourse', 'tool_coursecompliance', format_string($course->fullname)),
                ]),
                ['class' => 'cc-course-picker-check']
            );
            $categoryname = isset($categories[(int)$course->category])
                ? format_string($categories[(int)$course->category]->name)
                : '';
            $coursemeta = s((string)$course->shortname);
            if ($categoryname !== '') {
                $coursemeta .= ' · ' . $categoryname;
            }
            $html .= \html_writer::tag(
                'td',
                \html_writer::tag('strong', format_string($course->fullname)) .
                    \html_writer::div($coursemeta, 'small text-muted')
            );
            $html .= \html_writer::tag('td', $date);
            $html .= \html_writer::tag(
                'td',
                \html_writer::span($status, 'badge ' . $statusclass) .
                    ($details !== '' ? \html_writer::div($details, 'small text-muted mt-1') : '')
            );
            $html .= \html_writer::end_tag('tr');
        }
        $html .= \html_writer::end_tag('tbody');
        $html .= \html_writer::end_tag('table');
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();
        return $html;
    }
}

