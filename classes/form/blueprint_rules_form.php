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
 * Visual Blueprint rule constructor form.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to configure explicit template checks.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class blueprint_rules_form extends \moodleform {
    /** @var string[] Quiz review setting fields. */
    private const REVIEW_FIELDS = [
        'reviewattempt',
        'reviewcorrectness',
        'reviewmaxmarks',
        'reviewmarks',
        'reviewspecificfeedback',
        'reviewgeneralfeedback',
        'reviewrightanswer',
        'reviewoverallfeedback',
    ];

    /** @var string[] Quiz review periods. */
    private const REVIEW_PERIODS = ['during', 'immediately', 'open', 'closed'];

    /**
     * Define form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $profileid = (int)($this->_customdata['profileid'] ?? 0);

        $mform->addElement('hidden', 'profileid', $profileid);
        $mform->setType('profileid', PARAM_INT);
        $mform->addElement('advcheckbox', 'rules_enabled', get_string('blueprintrulesenabled', 'tool_coursecompliance'));

        $this->add_course_settings();
        $this->add_content_settings();
        foreach (['exam', 'resit1', 'resit2'] as $type) {
            $this->add_assessment_settings($type);
        }
        $this->add_gradebook_settings();
        $this->add_completion_settings();
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Add block A: course settings.
     */
    private function add_course_settings(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'courseblock', get_string('blueprintblock_course', 'tool_coursecompliance'));
        $settings = [
            'showactivitydates' => 'blueprint_showactivitydates',
            'maxbytes' => 'blueprint_maxbytes',
            'enablecompletion' => 'blueprint_enablecompletion',
            'groupmode' => 'blueprint_groupmode',
            'groupmodeforce' => 'blueprint_groupmodeforce',
        ];
        foreach ($settings as $field => $label) {
            $this->add_source_select('course_' . $field . '_source', get_string($label, 'tool_coursecompliance'));
            $manualfield = 'course_' . $field . '_manual';
            if ($field === 'maxbytes') {
                $mform->addElement(
                    'text',
                    $manualfield,
                    get_string('manualvalue', 'tool_coursecompliance'),
                    ['size' => 14]
                );
                $mform->setType($manualfield, PARAM_INT);
            } else if ($field === 'groupmode') {
                $mform->addElement(
                    'select',
                    $manualfield,
                    get_string('manualvalue', 'tool_coursecompliance'),
                    self::groupmode_options()
                );
            } else {
                $mform->addElement(
                    'selectyesno',
                    $manualfield,
                    get_string('manualvalue', 'tool_coursecompliance')
                );
            }
            $mform->disabledIf($manualfield, 'course_' . $field . '_source', 'neq', 'manual');
        }
    }

    /**
     * Add block B: course content and counts.
     */
    private function add_content_settings(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'contentblock', get_string('blueprintblock_content', 'tool_coursecompliance'));
        $this->add_count_rule('ordinaryquiz', get_string('ordinaryquizcount', 'tool_coursecompliance'));
        $this->add_count_rule('page', get_string('pagecount', 'tool_coursecompliance'));

        $mform->addElement(
            'text',
            'content_pagekeyword',
            get_string('pagekeyword', 'tool_coursecompliance'),
            ['size' => 40]
        );
        $mform->setType('content_pagekeyword', PARAM_TEXT);
        $this->add_count_rule('pagekeyword', get_string('pagekeywordcount', 'tool_coursecompliance'));

        $mform->addElement(
            'textarea',
            'content_literature_aliases',
            get_string('literaturealiases', 'tool_coursecompliance'),
            ['rows' => 5, 'cols' => 70]
        );
        $mform->setType('content_literature_aliases', PARAM_TEXT);
        $mform->addElement(
            'select',
            'content_literature_mode',
            get_string('literaturemode', 'tool_coursecompliance'),
            [
                'atleastone' => get_string('literaturemode_any', 'tool_coursecompliance'),
                'all' => get_string('literaturemode_all', 'tool_coursecompliance'),
            ]
        );
        $mform->addElement(
            'select',
            'content_section_source',
            get_string('sectioncheck', 'tool_coursecompliance'),
            [
                'ignore' => get_string('check_ignore', 'tool_coursecompliance'),
                'template' => get_string('check_template', 'tool_coursecompliance'),
            ]
        );
        foreach (['file', 'folder', 'url', 'book', 'assign', 'forum'] as $type) {
            $modulelabel = get_string('modulelabel_' . $type, 'tool_coursecompliance');
            $this->add_count_rule($type, get_string('modulecount', 'tool_coursecompliance', $modulelabel));
        }
        $mform->addElement(
            'advcheckbox',
            'content_uniqueidnumbers',
            get_string('uniqueidnumbers', 'tool_coursecompliance')
        );
    }

    /**
     * Add block C for one exam/resit target.
     *
     * @param string $type Assessment type.
     */
    private function add_assessment_settings(string $type): void {
        $mform = $this->_form;
        $prefix = 'assessment_' . $type . '_';
        $mform->addElement('header', $type . 'block', get_string('blueprintblock_' . $type, 'tool_coursecompliance'));
        $mform->addElement(
            'advcheckbox',
            $prefix . 'required',
            get_string('assessmentrequired', 'tool_coursecompliance')
        );
        $config = $this->_customdata['config'] ?? [];
        $targetidnumber = (string)($config[$type . 'idnumber'] ?? $type);
        $mform->addElement(
            'static',
            $prefix . 'idnumbernote',
            get_string('idnumber'),
            get_string('assessmentidnumbernote', 'tool_coursecompliance', $targetidnumber)
        );

        $this->add_assessment_scalar($prefix, 'gradepass', get_string('mingradepass', 'tool_coursecompliance'), 'float');
        $this->add_assessment_scalar($prefix, 'grade', get_string('expectedquizgrade', 'tool_coursecompliance'), 'float');
        $this->add_assessment_scalar($prefix, 'attempts', get_string('attemptsallowed', 'quiz'), 'int');
        $this->add_assessment_scalar($prefix, 'timelimit', get_string('timelimit', 'quiz'), 'duration');
        $this->add_assessment_scalar($prefix, 'grademethod', get_string('grademethod', 'quiz'), 'int');
        $this->add_assessment_scalar($prefix, 'shuffleanswers', get_string('shufflewithin', 'quiz'), 'bool');
        $this->add_assessment_scalar($prefix, 'groupmode', get_string('groupmode'), 'groupmode');
        $this->add_assessment_scalar($prefix, 'visibility', get_string('visible'), 'bool');

        $this->add_source_select(
            $prefix . 'availability_source',
            get_string('availabilityconditions', 'tool_coursecompliance')
        );
        $mform->addElement(
            'textarea',
            $prefix . 'availability_manual',
            get_string('manualjson', 'tool_coursecompliance'),
            ['rows' => 4, 'cols' => 80]
        );
        $mform->setType($prefix . 'availability_manual', PARAM_RAW_TRIMMED);
        $mform->disabledIf($prefix . 'availability_manual', $prefix . 'availability_source', 'neq', 'manual');

        $this->add_source_select(
            $prefix . 'completion_source',
            get_string('completionconditions', 'tool_coursecompliance')
        );
        $mform->addElement(
            'textarea',
            $prefix . 'completion_manual',
            get_string('manualjson', 'tool_coursecompliance'),
            ['rows' => 4, 'cols' => 80]
        );
        $mform->setType($prefix . 'completion_manual', PARAM_RAW_TRIMMED);
        $mform->disabledIf($prefix . 'completion_manual', $prefix . 'completion_source', 'neq', 'manual');

        $this->add_source_select($prefix . 'review_source', get_string('reviewoptions', 'quiz'));
        $this->add_review_matrix($prefix);

        $this->add_assessment_presence($prefix, 'password', get_string('requirepassword', 'tool_coursecompliance'));
        $this->add_assessment_presence($prefix, 'subnet', get_string('requiresubnet', 'tool_coursecompliance'));
        $this->add_source_select($prefix . 'browsersecurity_source', get_string('browsersecurity', 'quiz'));
        $mform->addElement(
            'text',
            $prefix . 'browsersecurity_manual',
            get_string('manualvalue', 'tool_coursecompliance'),
            ['size' => 32]
        );
        $mform->setType($prefix . 'browsersecurity_manual', PARAM_ALPHANUMEXT);
        $mform->disabledIf(
            $prefix . 'browsersecurity_manual',
            $prefix . 'browsersecurity_source',
            'neq',
            'manual'
        );

        $mform->addElement(
            'select',
            $prefix . 'dates_source',
            get_string('assessmentdatescheck', 'tool_coursecompliance'),
            [
                'ignore' => get_string('check_ignore', 'tool_coursecompliance'),
                'template' => get_string('check_template_presence', 'tool_coursecompliance'),
                'required' => get_string('check_required_dates', 'tool_coursecompliance'),
            ]
        );
        $mform->addElement(
            'text',
            $prefix . 'questions_min',
            get_string('minquestions', 'tool_coursecompliance'),
            ['size' => 6]
        );
        $mform->setType($prefix . 'questions_min', PARAM_INT);
        $mform->addElement(
            'text',
            $prefix . 'questions_max',
            get_string('maxquestions', 'tool_coursecompliance'),
            ['size' => 6]
        );
        $mform->setType($prefix . 'questions_max', PARAM_INT);
        $mform->addElement(
            'advcheckbox',
            $prefix . 'zero_mark_forbidden',
            get_string('zeromarkforbidden', 'tool_coursecompliance')
        );
    }

    /**
     * Add block G: gradebook checks.
     */
    private function add_gradebook_settings(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'gradebookblock', get_string('blueprintblock_gradebook', 'tool_coursecompliance'));
        $this->add_source_select(
            'gradebook_coursegrademax_source',
            get_string('coursegrademax', 'tool_coursecompliance')
        );
        $mform->addElement(
            'text',
            'gradebook_coursegrademax_manual',
            get_string('manualvalue', 'tool_coursecompliance'),
            ['size' => 10]
        );
        $mform->setType('gradebook_coursegrademax_manual', PARAM_FLOAT);
        $mform->disabledIf(
            'gradebook_coursegrademax_manual',
            'gradebook_coursegrademax_source',
            'neq',
            'manual'
        );

        $this->add_source_select('gradebook_formula_source', get_string('gradeformula', 'tool_coursecompliance'));
        $mform->addElement(
            'text',
            'gradebook_formula_idnumber',
            get_string('gradeformulaidnumber', 'tool_coursecompliance'),
            ['size' => 30]
        );
        $mform->setType('gradebook_formula_idnumber', PARAM_ALPHANUMEXT);
        $mform->addElement(
            'textarea',
            'gradebook_formula_manual',
            get_string('manualformula', 'tool_coursecompliance'),
            ['rows' => 3, 'cols' => 80]
        );
        $mform->setType('gradebook_formula_manual', PARAM_RAW_TRIMMED);
        $mform->disabledIf('gradebook_formula_manual', 'gradebook_formula_source', 'neq', 'manual');
    }

    /**
     * Add block D: course completion.
     */
    private function add_completion_settings(): void {
        $mform = $this->_form;
        $mform->addElement(
            'header',
            'completionblock',
            get_string('blueprintblock_completion', 'tool_coursecompliance')
        );
        $this->add_source_select(
            'completion_source',
            get_string('coursecompletionconditions', 'tool_coursecompliance')
        );
        $mform->addElement(
            'textarea',
            'completion_manual',
            get_string('manualjson', 'tool_coursecompliance'),
            ['rows' => 5, 'cols' => 80]
        );
        $mform->setType('completion_manual', PARAM_RAW_TRIMMED);
        $mform->disabledIf('completion_manual', 'completion_source', 'neq', 'manual');
    }

    /**
     * Add a template/manual/ignore selector.
     *
     * @param string $name Element name.
     * @param string $label Element label.
     */
    private function add_source_select(string $name, string $label): void {
        $this->_form->addElement(
            'select',
            $name,
            $label,
            [
                'ignore' => get_string('check_ignore', 'tool_coursecompliance'),
                'template' => get_string('check_template', 'tool_coursecompliance'),
                'manual' => get_string('check_manual', 'tool_coursecompliance'),
            ]
        );
    }

    /**
     * Add count comparison controls.
     *
     * @param string $key Rule key.
     * @param string $label Label.
     */
    private function add_count_rule(string $key, string $label): void {
        $mform = $this->_form;
        $mform->addElement(
            'select',
            'content_' . $key . '_mode',
            $label,
            [
                'ignore' => get_string('check_ignore', 'tool_coursecompliance'),
                'template' => get_string('check_template', 'tool_coursecompliance'),
                'exact' => get_string('check_exact', 'tool_coursecompliance'),
                'min' => get_string('check_minimum', 'tool_coursecompliance'),
                'max' => get_string('check_maximum', 'tool_coursecompliance'),
            ]
        );
        $mform->addElement(
            'text',
            'content_' . $key . '_value',
            get_string('countvalue', 'tool_coursecompliance'),
            ['size' => 6]
        );
        $mform->setType('content_' . $key . '_value', PARAM_INT);
        $mform->disabledIf('content_' . $key . '_value', 'content_' . $key . '_mode', 'eq', 'ignore');
        $mform->disabledIf('content_' . $key . '_value', 'content_' . $key . '_mode', 'eq', 'template');
    }

    /**
     * Add scalar assessment controls.
     *
     * @param string $prefix Field prefix.
     * @param string $key Setting key.
     * @param string $label Label.
     * @param string $type Value type.
     */
    private function add_assessment_scalar(string $prefix, string $key, string $label, string $type): void {
        $mform = $this->_form;
        $this->add_source_select($prefix . $key . '_source', $label);
        $name = $prefix . $key . '_manual';
        if ($type === 'bool') {
            $mform->addElement('selectyesno', $name, get_string('manualvalue', 'tool_coursecompliance'));
        } else if ($type === 'groupmode') {
            $mform->addElement(
                'select',
                $name,
                get_string('manualvalue', 'tool_coursecompliance'),
                self::groupmode_options()
            );
        } else if ($type === 'duration') {
            $mform->addElement(
                'duration',
                $name,
                get_string('manualvalue', 'tool_coursecompliance'),
                ['optional' => true]
            );
        } else {
            $mform->addElement(
                'text',
                $name,
                get_string('manualvalue', 'tool_coursecompliance'),
                ['size' => 10]
            );
            $mform->setType($name, $type === 'float' ? PARAM_FLOAT : PARAM_INT);
        }
        $mform->disabledIf($name, $prefix . $key . '_source', 'neq', 'manual');
    }

    /**
     * Add presence-only assessment setting.
     *
     * @param string $prefix Field prefix.
     * @param string $key Setting key.
     * @param string $label Label.
     */
    private function add_assessment_presence(string $prefix, string $key, string $label): void {
        $mform = $this->_form;
        $this->add_source_select($prefix . $key . '_source', $label);
        $mform->addElement(
            'selectyesno',
            $prefix . $key . '_manual',
            get_string('manualpresence', 'tool_coursecompliance')
        );
        $mform->disabledIf($prefix . $key . '_manual', $prefix . $key . '_source', 'neq', 'manual');
    }

    /**
     * Add a visual Quiz review-options matrix.
     *
     * @param string $prefix Field prefix.
     */
    private function add_review_matrix(string $prefix): void {
        $mform = $this->_form;
        $mform->addElement(
            'static',
            $prefix . 'reviewhelp',
            '',
            get_string('reviewmatrixhelp', 'tool_coursecompliance')
        );
        foreach (self::REVIEW_FIELDS as $field) {
            $elements = [];
            foreach (self::REVIEW_PERIODS as $period) {
                $name = $prefix . 'review_' . $field . '_' . $period;
                $elements[] = $mform->createElement(
                    'advcheckbox',
                    $name,
                    get_string('reviewperiod_' . $period, 'tool_coursecompliance')
                );
            }
            $mform->addGroup(
                $elements,
                $prefix . 'group_' . $field,
                get_string('reviewfield_' . $field, 'tool_coursecompliance'),
                [' '],
                false
            );
            foreach (self::REVIEW_PERIODS as $period) {
                $mform->disabledIf(
                    $prefix . 'review_' . $field . '_' . $period,
                    $prefix . 'review_source',
                    'neq',
                    'manual'
                );
            }
        }
    }

    /**
     * Validate manual JSON and numeric ranges.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array<string, string>
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (['exam', 'resit1', 'resit2'] as $type) {
            $prefix = 'assessment_' . $type . '_';
            foreach (['availability', 'completion'] as $field) {
                if (($data[$prefix . $field . '_source'] ?? '') !== 'manual') {
                    continue;
                }
                $value = trim((string)($data[$prefix . $field . '_manual'] ?? ''));
                if ($value !== '' && json_decode($value, true) === null && strtolower($value) !== 'null') {
                    $errors[$prefix . $field . '_manual'] = get_string('invalidjson', 'tool_coursecompliance');
                }
            }
            $min = (int)($data[$prefix . 'questions_min'] ?? 0);
            $max = (int)($data[$prefix . 'questions_max'] ?? 0);
            if ($min < 0 || $max < 0 || ($max > 0 && $max < $min)) {
                $errors[$prefix . 'questions_max'] = get_string('invalidquestionrange', 'tool_coursecompliance');
            }
        }
        if (($data['completion_source'] ?? '') === 'manual') {
            $value = trim((string)($data['completion_manual'] ?? ''));
            if ($value !== '' && !is_array(json_decode($value, true))) {
                $errors['completion_manual'] = get_string('invalidjson', 'tool_coursecompliance');
            }
        }
        foreach ($data as $key => $value) {
            if (str_ends_with((string)$key, '_value') && (int)$value < 0) {
                $errors[$key] = get_string('positivevalueexpected', 'tool_coursecompliance');
            }
        }
        return $errors;
    }

    /**
     * Return group mode options.
     *
     * @return array<int, string>
     */
    private static function groupmode_options(): array {
        return [
            0 => get_string('groupmodenone', 'tool_coursecompliance'),
            1 => get_string('groupmodeseparate', 'tool_coursecompliance'),
            2 => get_string('groupmodevisible', 'tool_coursecompliance'),
        ];
    }
}
