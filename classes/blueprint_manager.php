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
 * Blueprint extraction and comparison support.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Extracts immutable course blueprints and compares course state in bulk.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class blueprint_manager {
    /** Review period bit used by Quiz. */
    private const REVIEW_DURING = 0x10000;

    /** Review period bit used immediately after an attempt. */
    private const REVIEW_IMMEDIATELY = 0x1000;

    /** Review period bit used while the Quiz is still open. */
    private const REVIEW_OPEN = 0x100;

    /** Review period bit used after the Quiz closes. */
    private const REVIEW_CLOSED = 0x10;

    /** @var string[] Quiz review fields supported by the Blueprint editor. */
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

    /**
     * Extract a reusable Blueprint from a course.
     *
     * @param int $courseid Source course id.
     * @return array<string, mixed>
     */
    public static function extract(int $courseid): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $states = self::load_states([$courseid]);
        $state = $states[$courseid] ?? self::empty_state();
        return [
            'schema' => 2,
            'sourcecourseid' => $courseid,
            'sourcefullname' => (string)$course->fullname,
            'sourceshortname' => (string)$course->shortname,
            'capturedat' => time(),
            'course' => [
                'showactivitydates' => (int)($course->showactivitydates ?? 0),
                'maxbytes' => (int)$course->maxbytes,
                'enablecompletion' => (int)$course->enablecompletion,
                'groupmode' => (int)$course->groupmode,
                'groupmodeforce' => (int)$course->groupmodeforce,
            ],
            'sections' => $state['sections'],
            'activities' => self::unique_blueprint_activities($state['activities']),
            'inventory' => self::snapshot_inventory($state['inventory']),
            'counts' => $state['counts'],
            'gradeformulas' => $state['gradeformulas'],
            'coursegrade' => $state['coursegrade'],
            'completioncriteria' => $state['completioncriteria'],
        ];
    }

    /**
     * Load comparison state for many courses using bounded bulk queries.
     *
     * @param int[] $courseids Course ids.
     * @return array<int, array<string, mixed>>
     */
    public static function load_states(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (!$courseids) {
            return [];
        }
        if (count($courseids) > 400) {
            $states = [];
            foreach (array_chunk($courseids, 400) as $chunk) {
                $states += self::load_states($chunk);
            }
            return $states;
        }

        $states = [];
        foreach ($courseids as $courseid) {
            $states[$courseid] = self::empty_state();
        }
        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'bpc');

        $sections = $DB->get_records_select('course_sections', "course $courseinsql", $courseparams, 'course, section');
        foreach ($sections as $section) {
            $name = trim((string)$section->name);
            if ($name === '') {
                continue;
            }
            $states[(int)$section->course]['sections'][] = [
                'section' => (int)$section->section,
                'name' => $name,
            ];
        }

        $sql = "SELECT cm.id, cm.course, cm.module, cm.instance, cm.idnumber, cm.availability,
                       cm.completion, cm.completiongradeitemnumber, cm.completionview,
                       cm.completionexpected, cm.completionpassgrade, cm.visible, cm.groupmode,
                       cm.groupingid, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course $courseinsql";
        $cms = $DB->get_records_sql($sql, $courseparams);
        $instancesbymod = [];
        $cmidmaps = [];
        foreach ($cms as $cm) {
            $courseid = (int)$cm->course;
            $cmidmaps[$courseid][(int)$cm->id] = trim((string)$cm->idnumber);
            $instancesbymod[(string)$cm->modname][] = (int)$cm->instance;
        }

        $instancenames = self::load_instance_names($instancesbymod);

        $gradeitems = $DB->get_records_select(
            'grade_items',
            "courseid $courseinsql",
            $courseparams,
            'courseid, id'
        );
        $gradeidmaps = [];
        $quizgradeitems = [];
        $coursegradeitems = [];
        foreach ($gradeitems as $item) {
            $courseid = (int)$item->courseid;
            $idnumber = trim((string)$item->idnumber);
            $gradeidmaps[$courseid][(int)$item->id] = $idnumber;
            if ((string)$item->itemmodule === 'quiz' && (int)$item->itemnumber === 0) {
                $quizgradeitems[$courseid][(int)$item->iteminstance] = $item;
            }
            if ((string)$item->itemtype === 'course') {
                $coursegradeitems[$courseid] = $item;
            }
        }
        foreach ($coursegradeitems as $courseid => $item) {
            $states[$courseid]['coursegrade'] = [
                'grademax' => (float)$item->grademax,
                'gradepass' => (float)$item->gradepass,
                'calculation' => self::normalise_formula(
                    (string)($item->calculation ?? ''),
                    $gradeidmaps[$courseid] ?? []
                ),
            ];
        }

        $groupmaps = self::load_group_maps($courseids);
        $groupingmaps = self::load_grouping_maps($courseids);
        $quizsettings = self::load_quiz_settings($courseids, $courseinsql, $courseparams, $quizgradeitems);

        foreach ($cms as $cm) {
            $courseid = (int)$cm->course;
            $idnumber = trim((string)$cm->idnumber);
            $modname = (string)$cm->modname;
            $name = $instancenames[$modname][(int)$cm->instance] ?? '';
            $activity = [
                'cmid' => (int)$cm->id,
                'instanceid' => (int)$cm->instance,
                'idnumber' => $idnumber,
                'modname' => $modname,
                'name' => $name,
                'visible' => (int)$cm->visible,
                'groupmode' => (int)$cm->groupmode,
                'grouping' => $groupingmaps[$courseid][(int)$cm->groupingid] ?? '',
                'availability' => self::normalise_availability(
                    (string)($cm->availability ?? ''),
                    $cmidmaps[$courseid] ?? [],
                    $gradeidmaps[$courseid] ?? [],
                    $groupmaps[$courseid] ?? [],
                    $groupingmaps[$courseid] ?? []
                ),
                'completion' => [
                    'completion' => (int)$cm->completion,
                    'completiongradeitemnumber' => $cm->completiongradeitemnumber === null
                        ? null
                        : (int)$cm->completiongradeitemnumber,
                    'completionview' => (int)$cm->completionview,
                    'completionpassgrade' => (int)$cm->completionpassgrade,
                ],
                'settings' => $modname === 'quiz' ? ($quizsettings[(int)$cm->instance] ?? []) : [],
            ];
            $states[$courseid]['inventory'][] = $activity;
            $states[$courseid]['counts'][$modname] = ($states[$courseid]['counts'][$modname] ?? 0) + 1;
            if ($idnumber !== '') {
                $states[$courseid]['activities'][$idnumber][] = $activity;
            }
        }

        foreach ($gradeitems as $item) {
            $courseid = (int)$item->courseid;
            $idnumber = trim((string)$item->idnumber);
            $calculation = trim((string)($item->calculation ?? ''));
            if ($idnumber === '' || $calculation === '') {
                continue;
            }
            $states[$courseid]['gradeformulas'][$idnumber] = self::normalise_formula(
                $calculation,
                $gradeidmaps[$courseid] ?? []
            );
        }

        $criteria = $DB->get_records_select(
            'course_completion_criteria',
            "course $courseinsql",
            $courseparams,
            'course, id'
        );
        foreach ($criteria as $criterion) {
            $courseid = (int)$criterion->course;
            $states[$courseid]['completioncriteria'][] = [
                'criteriatype' => (int)$criterion->criteriatype,
                'module' => (string)($criterion->module ?? ''),
                'moduleidnumber' => !empty($criterion->moduleinstance)
                    ? ($cmidmaps[$courseid][(int)$criterion->moduleinstance] ?? '')
                    : '',
                'courseinstance' => (int)($criterion->courseinstance ?? 0),
                'enrolperiod' => (int)($criterion->enrolperiod ?? 0),
                'timeend' => (int)($criterion->timeend ?? 0),
                'gradepass' => $criterion->gradepass === null ? null : (float)$criterion->gradepass,
                'role' => (int)($criterion->role ?? 0),
            ];
        }

        foreach ($states as &$state) {
            usort($state['sections'], static fn ($a, $b) => $a['section'] <=> $b['section']);
            usort($state['inventory'], static fn ($a, $b) => $a['cmid'] <=> $b['cmid']);
            ksort($state['activities']);
            ksort($state['counts']);
            ksort($state['gradeformulas']);
            usort(
                $state['completioncriteria'],
                static fn ($a, $b) => strcmp(json_encode($a), json_encode($b))
            );
        }
        unset($state);
        return $states;
    }

    /**
     * Compare a course with a stored Blueprint.
     *
     * @param \stdClass $course Course record.
     * @param array $state Loaded course state.
     * @param array $blueprint Stored Blueprint.
     * @param array $config Profile configuration.
     * @return array<int, array<string, mixed>>
     */
    public static function compare(\stdClass $course, array $state, array $blueprint, array $config): array {
        $rules = $config['blueprintrules'] ?? [];
        if (is_array($rules) && !empty($rules['enabled']) && (int)($blueprint['schema'] ?? 1) >= 2) {
            return self::compare_rules($course, $state, $blueprint, $config, $rules);
        }
        return self::compare_legacy($course, $state, $blueprint, $config);
    }

    /**
     * Compare the explicit visual rule set.
     *
     * @param \stdClass $course Course record.
     * @param array $state Course state.
     * @param array $blueprint Blueprint snapshot.
     * @param array $config Profile configuration.
     * @param array $rules Visual rules.
     * @return array<int, array<string, mixed>>
     */
    private static function compare_rules(
        \stdClass $course,
        array $state,
        array $blueprint,
        array $config,
        array $rules
    ): array {
        $problems = [];
        $courserules = $rules['course'] ?? [];
        foreach (['showactivitydates', 'maxbytes', 'enablecompletion', 'groupmode', 'groupmodeforce'] as $field) {
            $source = (string)($courserules[$field . '_source'] ?? 'ignore');
            if ($source === 'ignore') {
                continue;
            }
            $expected = $source === 'template'
                ? ($blueprint['course'][$field] ?? null)
                : ($courserules[$field . '_manual'] ?? null);
            $actual = $course->{$field} ?? null;
            if ((string)$actual === (string)$expected) {
                continue;
            }
            $problems[] = self::problem(
                'course',
                'BLUEPRINT_COURSE_' . strtoupper($field),
                'warning',
                get_string('v_blueprint_course_setting', 'tool_coursecompliance', $field),
                self::display_scalar($field, $expected),
                self::display_scalar($field, $actual)
            );
        }

        $content = $rules['content'] ?? [];
        $targetids = array_filter(
            [
                trim((string)($config['examidnumber'] ?? 'exam')),
                trim((string)($config['resit1idnumber'] ?? 'resit1')),
                trim((string)($config['resit2idnumber'] ?? 'resit2')),
            ]
        );
        $ordinaryactual = self::count_ordinary_quizzes($state['inventory'] ?? [], $targetids);
        $ordinarytemplate = self::count_ordinary_quizzes($blueprint['inventory'] ?? [], $targetids);
        self::compare_count_rule(
            $problems,
            'BLUEPRINT_ORDINARY_QUIZ_COUNT',
            'quiz',
            get_string('v_blueprint_ordinaryquiz_count', 'tool_coursecompliance'),
            (string)($content['ordinaryquiz_mode'] ?? 'ignore'),
            (int)($content['ordinaryquiz_value'] ?? 0),
            $ordinarytemplate,
            $ordinaryactual
        );
        self::compare_count_rule(
            $problems,
            'BLUEPRINT_PAGE_COUNT',
            'page',
            get_string('v_blueprint_page_count', 'tool_coursecompliance'),
            (string)($content['page_mode'] ?? 'ignore'),
            (int)($content['page_value'] ?? 0),
            (int)($blueprint['counts']['page'] ?? 0),
            (int)($state['counts']['page'] ?? 0)
        );

        $keyword = trim((string)($content['pagekeyword'] ?? ''));
        if ($keyword !== '') {
            $actual = self::count_named_pages($state['inventory'] ?? [], $keyword, false);
            $template = self::count_named_pages($blueprint['inventory'] ?? [], $keyword, false);
            self::compare_count_rule(
                $problems,
                'BLUEPRINT_PAGE_KEYWORD_COUNT',
                'page',
                get_string('v_blueprint_page_keyword', 'tool_coursecompliance', $keyword),
                (string)($content['pagekeyword_mode'] ?? 'ignore'),
                (int)($content['pagekeyword_value'] ?? 1),
                $template,
                $actual
            );
        }

        self::compare_literature_pages($problems, $state['inventory'] ?? [], $content);
        foreach (['resource', 'folder', 'url', 'book', 'assign', 'forum'] as $modname) {
            $key = $modname === 'resource' ? 'file' : $modname;
            $modulelabel = get_string('modulelabel_' . $key, 'tool_coursecompliance');
            self::compare_count_rule(
                $problems,
                'BLUEPRINT_' . strtoupper($key) . '_COUNT',
                $modname,
                get_string('v_blueprint_module_count', 'tool_coursecompliance', $modulelabel),
                (string)($content[$key . '_mode'] ?? 'ignore'),
                (int)($content[$key . '_value'] ?? 0),
                (int)($blueprint['counts'][$modname] ?? 0),
                (int)($state['counts'][$modname] ?? 0)
            );
        }

        if (($content['section_source'] ?? 'ignore') === 'template') {
            self::compare_sections($problems, $state['sections'] ?? [], $blueprint['sections'] ?? []);
        }
        if (!empty($content['uniqueidnumbers'])) {
            foreach (($state['activities'] ?? []) as $idnumber => $matches) {
                if (count($matches) <= 1) {
                    continue;
                }
                $problems[] = self::problem(
                    'course',
                    'BLUEPRINT_DUPLICATE_IDNUMBER',
                    'critical',
                    get_string('v_blueprint_duplicate_idnumber', 'tool_coursecompliance', $idnumber),
                    '1',
                    (string)count($matches)
                );
            }
        }

        foreach (['exam', 'resit1', 'resit2'] as $type) {
            $assessmentrules = $rules['assessments'][$type] ?? [];
            $idnumber = trim((string)($config[$type . 'idnumber'] ?? $type));
            self::compare_assessment(
                $problems,
                $type,
                $idnumber,
                $state['activities'][$idnumber] ?? [],
                $blueprint['activities'][$idnumber] ?? null,
                $assessmentrules
            );
        }

        self::compare_gradebook($problems, $state, $blueprint, $rules['gradebook'] ?? []);
        self::compare_course_completion($problems, $state, $blueprint, $rules['completion'] ?? []);
        return $problems;
    }

    /**
     * Compare one exam/resit target.
     *
     * @param array $problems Problem collector.
     * @param string $type Assessment type.
     * @param string $idnumber Required idnumber.
     * @param array $matches Matching activities in target course.
     * @param array|null $template Template activity.
     * @param array $rules Rules for this assessment.
     */
    private static function compare_assessment(
        array &$problems,
        string $type,
        string $idnumber,
        array $matches,
        ?array $template,
        array $rules
    ): void {
        if (empty($rules['required']) && !$matches) {
            return;
        }
        if (!$matches) {
            $problems[] = self::problem(
                'quiz',
                'BLUEPRINT_ASSESSMENT_MISSING',
                'critical',
                get_string('v_blueprint_assessment_missing', 'tool_coursecompliance', $idnumber),
                get_string('present', 'tool_coursecompliance'),
                get_string('missing', 'tool_coursecompliance'),
                0,
                '',
                $type
            );
            return;
        }
        if (count($matches) > 1) {
            $problems[] = self::problem(
                'quiz',
                'BLUEPRINT_ASSESSMENT_DUPLICATE',
                'critical',
                get_string('v_blueprint_assessment_duplicate', 'tool_coursecompliance', $idnumber),
                '1',
                (string)count($matches),
                (int)$matches[0]['instanceid'],
                (string)$matches[0]['name'],
                $type
            );
            return;
        }
        $actual = $matches[0];
        if ((string)$actual['modname'] !== 'quiz') {
            $problems[] = self::problem(
                (string)$actual['modname'],
                'BLUEPRINT_ASSESSMENT_TYPE',
                'critical',
                get_string('v_blueprint_activity_type', 'tool_coursecompliance', $idnumber),
                'quiz',
                (string)$actual['modname'],
                (int)$actual['instanceid'],
                (string)$actual['name'],
                $type
            );
            return;
        }
        $settings = $actual['settings'] ?? [];
        $templatesettings = $template['settings'] ?? [];
        $context = [
            'objectid' => (int)$actual['instanceid'],
            'objectname' => (string)$actual['name'],
            'type' => $type,
            'idnumber' => $idnumber,
        ];

        $scalarsettings = [
            'gradepass' => 'float',
            'grade' => 'float',
            'attempts' => 'int',
            'timelimit' => 'duration',
            'grademethod' => 'int',
            'shuffleanswers' => 'bool',
        ];
        foreach ($scalarsettings as $field => $displaytype) {
            self::compare_assessment_scalar(
                $problems,
                $context,
                $field,
                $displaytype,
                $rules,
                $templatesettings,
                $settings
            );
        }

        self::compare_assessment_scalar(
            $problems,
            $context,
            'groupmode',
            'groupmode',
            $rules,
            $template ?? [],
            $actual
        );
        self::compare_assessment_scalar(
            $problems,
            $context,
            'visible',
            'bool',
            $rules,
            $template ?? [],
            $actual,
            'visibility'
        );

        self::compare_assessment_complex(
            $problems,
            $context,
            'availability',
            $rules,
            $template ?? [],
            $actual,
            static fn ($value): string => self::describe_availability((string)$value)
        );
        self::compare_assessment_completion(
            $problems,
            $context,
            $rules,
            $template ?? [],
            $actual,
            $templatesettings,
            $settings
        );

        self::compare_review_options($problems, $context, $rules, $templatesettings, $settings);

        foreach (['password', 'subnet'] as $field) {
            $source = (string)($rules[$field . '_source'] ?? 'ignore');
            if ($source === 'ignore') {
                continue;
            }
            $actualpresent = trim((string)($settings[$field] ?? '')) !== '' ? 1 : 0;
            $expected = $source === 'template'
                ? (trim((string)($templatesettings[$field] ?? '')) !== '' ? 1 : 0)
                : (int)($rules[$field . '_manual'] ?? 0);
            if ($actualpresent === $expected) {
                continue;
            }
            $problems[] = self::assessment_problem(
                $context,
                'BLUEPRINT_ASSESSMENT_' . strtoupper($field),
                get_string('v_blueprint_assessment_setting', 'tool_coursecompliance', $field),
                self::yesno($expected),
                self::yesno($actualpresent)
            );
        }

        $source = (string)($rules['browsersecurity_source'] ?? 'ignore');
        if ($source !== 'ignore') {
            $expected = $source === 'template'
                ? (string)($templatesettings['browsersecurity'] ?? '')
                : (string)($rules['browsersecurity_manual'] ?? '');
            $actualvalue = (string)($settings['browsersecurity'] ?? '');
            if ($expected !== $actualvalue) {
                $problems[] = self::assessment_problem(
                    $context,
                    'BLUEPRINT_ASSESSMENT_BROWSERSECURITY',
                    get_string('v_blueprint_assessment_setting', 'tool_coursecompliance', 'browsersecurity'),
                    $expected === '' ? get_string('none', 'tool_coursecompliance') : $expected,
                    $actualvalue === '' ? get_string('none', 'tool_coursecompliance') : $actualvalue
                );
            }
        }

        $datesource = (string)($rules['dates_source'] ?? 'ignore');
        if ($datesource !== 'ignore') {
            $actualpattern = [!empty($settings['timeopen']), !empty($settings['timeclose'])];
            $expectedpattern = $datesource === 'template'
                ? [!empty($templatesettings['timeopen']), !empty($templatesettings['timeclose'])]
                : [true, true];
            if ($actualpattern !== $expectedpattern) {
                $problems[] = self::assessment_problem(
                    $context,
                    'BLUEPRINT_ASSESSMENT_DATES',
                    get_string('v_blueprint_assessment_dates', 'tool_coursecompliance'),
                    self::describe_date_pattern($expectedpattern),
                    self::describe_date_pattern($actualpattern)
                );
            }
        }

        $questioncount = (int)($settings['questioncount'] ?? 0);
        $min = (int)($rules['questions_min'] ?? 0);
        $max = (int)($rules['questions_max'] ?? 0);
        if ($min > 0 && $questioncount < $min) {
            $problems[] = self::assessment_problem(
                $context,
                'BLUEPRINT_ASSESSMENT_QUESTIONS_MIN',
                get_string('v_blueprint_questions_min', 'tool_coursecompliance'),
                '>= ' . $min,
                (string)$questioncount
            );
        }
        if ($max > 0 && $questioncount > $max) {
            $problems[] = self::assessment_problem(
                $context,
                'BLUEPRINT_ASSESSMENT_QUESTIONS_MAX',
                get_string('v_blueprint_questions_max', 'tool_coursecompliance'),
                '<= ' . $max,
                (string)$questioncount
            );
        }
        if (!empty($rules['zero_mark_forbidden']) && (int)($settings['zeromarkcount'] ?? 0) > 0) {
            $problems[] = self::assessment_problem(
                $context,
                'BLUEPRINT_ASSESSMENT_ZERO_MARKS',
                get_string('v_blueprint_zero_marks', 'tool_coursecompliance'),
                '0',
                (string)(int)$settings['zeromarkcount']
            );
        }
    }

    /**
     * Compare one scalar assessment setting.
     *
     * @param array $problems Problem collector.
     * @param array $context Assessment context.
     * @param string $field Actual field name.
     * @param string $displaytype Display type.
     * @param array $rules Assessment rules.
     * @param array $template Template settings.
     * @param array $actual Actual settings.
     * @param string|null $ruleprefix Optional rule key prefix.
     */
    private static function compare_assessment_scalar(
        array &$problems,
        array $context,
        string $field,
        string $displaytype,
        array $rules,
        array $template,
        array $actual,
        ?string $ruleprefix = null
    ): void {
        $key = $ruleprefix ?? $field;
        $source = (string)($rules[$key . '_source'] ?? 'ignore');
        if ($source === 'ignore') {
            return;
        }
        $expected = $source === 'template'
            ? ($template[$field] ?? null)
            : ($rules[$key . '_manual'] ?? null);
        $actualvalue = $actual[$field] ?? null;
        if ((string)$actualvalue === (string)$expected) {
            return;
        }
        $problems[] = self::assessment_problem(
            $context,
            'BLUEPRINT_ASSESSMENT_' . strtoupper($key),
            get_string('v_blueprint_assessment_setting', 'tool_coursecompliance', $key),
            self::display_typed($expected, $displaytype),
            self::display_typed($actualvalue, $displaytype)
        );
    }

    /**
     * Compare one complex assessment setting.
     *
     * @param array $problems Problem collector.
     * @param array $context Assessment context.
     * @param string $field Field name.
     * @param array $rules Assessment rules.
     * @param array $template Template settings.
     * @param array $actual Actual settings.
     * @param callable $formatter Human-readable formatter.
     */
    private static function compare_assessment_complex(
        array &$problems,
        array $context,
        string $field,
        array $rules,
        array $template,
        array $actual,
        callable $formatter
    ): void {
        $source = (string)($rules[$field . '_source'] ?? 'ignore');
        if ($source === 'ignore') {
            return;
        }
        if ($source === 'template') {
            $expected = $template[$field] ?? ($field === 'completion' ? [] : '');
        } else {
            $manual = (string)($rules[$field . '_manual'] ?? '');
            $decoded = json_decode($manual, true);
            $expected = $decoded === null && trim($manual) !== 'null' ? $manual : $decoded;
            if ($field === 'availability' && is_array($expected)) {
                self::recursive_ksort($expected);
                $expected = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $actualvalue = $actual[$field] ?? ($field === 'completion' ? [] : '');
        if ($actualvalue === $expected) {
            return;
        }
        $problems[] = self::assessment_problem(
            $context,
            'BLUEPRINT_ASSESSMENT_' . strtoupper($field),
            get_string('v_blueprint_assessment_setting', 'tool_coursecompliance', $field),
            $formatter($expected),
            $formatter($actualvalue)
        );
    }

    /**
     * Compare generic and Quiz-specific completion settings.
     *
     * @param array $problems Problem collector.
     * @param array $context Assessment context.
     * @param array $rules Assessment rules.
     * @param array $template Template activity.
     * @param array $actual Actual activity.
     * @param array $templatesettings Template Quiz settings.
     * @param array $settings Actual Quiz settings.
     */
    private static function compare_assessment_completion(
        array &$problems,
        array $context,
        array $rules,
        array $template,
        array $actual,
        array $templatesettings,
        array $settings
    ): void {
        $source = (string)($rules['completion_source'] ?? 'ignore');
        if ($source === 'ignore') {
            return;
        }
        $actualvalue = $actual['completion'] ?? [];
        $actualvalue['completionattemptsexhausted'] = (int)($settings['completionattemptsexhausted'] ?? 0);
        $actualvalue['completionminattempts'] = (int)($settings['completionminattempts'] ?? 0);

        if ($source === 'template') {
            $expected = $template['completion'] ?? [];
            $expected['completionattemptsexhausted'] = (int)($templatesettings['completionattemptsexhausted'] ?? 0);
            $expected['completionminattempts'] = (int)($templatesettings['completionminattempts'] ?? 0);
        } else {
            $manual = trim((string)($rules['completion_manual'] ?? ''));
            $expected = json_decode($manual, true);
            if (!is_array($expected)) {
                $expected = [];
            }
        }
        ksort($expected);
        ksort($actualvalue);
        if ($expected === $actualvalue) {
            return;
        }
        $problems[] = self::assessment_problem(
            $context,
            'BLUEPRINT_ASSESSMENT_COMPLETION',
            get_string('v_blueprint_assessment_setting', 'tool_coursecompliance', 'completion'),
            self::describe_completion($expected),
            self::describe_completion($actualvalue)
        );
    }

    /**
     * Compare Quiz review settings.
     *
     * @param array $problems Problem collector.
     * @param array $context Assessment context.
     * @param array $rules Assessment rules.
     * @param array $template Template Quiz settings.
     * @param array $actual Actual Quiz settings.
     */
    private static function compare_review_options(
        array &$problems,
        array $context,
        array $rules,
        array $template,
        array $actual
    ): void {
        $source = (string)($rules['review_source'] ?? 'ignore');
        if ($source === 'ignore') {
            return;
        }
        $expected = [];
        foreach (self::REVIEW_FIELDS as $field) {
            $expected[$field] = $source === 'template'
                ? (int)($template[$field] ?? 0)
                : self::encode_review_field($rules['review_manual'][$field] ?? []);
        }
        $actualvalues = [];
        foreach (self::REVIEW_FIELDS as $field) {
            $actualvalues[$field] = (int)($actual[$field] ?? 0);
        }
        if ($expected === $actualvalues) {
            return;
        }
        $problems[] = self::assessment_problem(
            $context,
            'BLUEPRINT_ASSESSMENT_REVIEW',
            get_string('v_blueprint_review_options', 'tool_coursecompliance'),
            self::describe_review($expected),
            self::describe_review($actualvalues)
        );
    }

    /**
     * Compare gradebook settings.
     *
     * @param array $problems Problem collector.
     * @param array $state Course state.
     * @param array $blueprint Blueprint snapshot.
     * @param array $rules Gradebook rules.
     */
    private static function compare_gradebook(array &$problems, array $state, array $blueprint, array $rules): void {
        $maxsource = (string)($rules['coursegrademax_source'] ?? 'ignore');
        if ($maxsource !== 'ignore') {
            $expected = $maxsource === 'template'
                ? (float)($blueprint['coursegrade']['grademax'] ?? 0)
                : (float)($rules['coursegrademax_manual'] ?? 0);
            $actual = (float)($state['coursegrade']['grademax'] ?? 0);
            if (abs($expected - $actual) > 0.00001) {
                $problems[] = self::problem(
                    'gradebook',
                    'BLUEPRINT_COURSE_GRADE_MAX',
                    'critical',
                    get_string('v_blueprint_course_grade_max', 'tool_coursecompliance'),
                    self::format_float($expected),
                    self::format_float($actual)
                );
            }
        }

        $formulasource = (string)($rules['formula_source'] ?? 'ignore');
        if ($formulasource === 'ignore') {
            return;
        }
        $idnumber = trim((string)($rules['formula_idnumber'] ?? ''));
        if ($idnumber === '') {
            $expected = $formulasource === 'template'
                ? (string)($blueprint['coursegrade']['calculation'] ?? '')
                : trim((string)($rules['formula_manual'] ?? ''));
            $actual = (string)($state['coursegrade']['calculation'] ?? '');
            if ($expected !== $actual) {
                $problems[] = self::problem(
                    'gradebook',
                    'BLUEPRINT_COURSE_GRADE_FORMULA',
                    'critical',
                    get_string('v_blueprint_course_grade_formula', 'tool_coursecompliance'),
                    $expected === '' ? get_string('none', 'tool_coursecompliance') : $expected,
                    $actual === '' ? get_string('none', 'tool_coursecompliance') : $actual
                );
            }
            return;
        }
        if ($formulasource === 'manual') {
            $expected = trim((string)($rules['formula_manual'] ?? ''));
            $actual = $state['gradeformulas'][$idnumber] ?? null;
            self::compare_formula($problems, $idnumber, $expected, $actual);
            return;
        }
        $templateformulas = $blueprint['gradeformulas'] ?? [];
        if (array_key_exists($idnumber, $templateformulas)) {
            self::compare_formula(
                $problems,
                $idnumber,
                (string)$templateformulas[$idnumber],
                $state['gradeformulas'][$idnumber] ?? null
            );
        }
    }

    /**
     * Compare a single grade formula.
     *
     * @param array $problems Problem collector.
     * @param string $idnumber Grade item idnumber.
     * @param string $expected Expected formula.
     * @param string|null $actual Actual formula.
     */
    private static function compare_formula(array &$problems, string $idnumber, string $expected, ?string $actual): void {
        if ($actual === $expected) {
            return;
        }
        $missing = $actual === null;
        $problems[] = self::problem(
            'gradebook',
            $missing ? 'BLUEPRINT_GRADE_FORMULA_MISSING' : 'BLUEPRINT_GRADE_FORMULA',
            'critical',
            get_string(
                $missing ? 'v_blueprint_grade_formula_missing' : 'v_blueprint_grade_formula',
                'tool_coursecompliance',
                $idnumber
            ),
            $expected,
            $missing ? get_string('missing', 'tool_coursecompliance') : (string)$actual
        );
    }

    /**
     * Compare course completion conditions.
     *
     * @param array $problems Problem collector.
     * @param array $state Course state.
     * @param array $blueprint Blueprint snapshot.
     * @param array $rules Completion rules.
     */
    private static function compare_course_completion(
        array &$problems,
        array $state,
        array $blueprint,
        array $rules
    ): void {
        $source = (string)($rules['source'] ?? 'ignore');
        if ($source === 'ignore') {
            return;
        }
        if ($source === 'template') {
            $expected = $blueprint['completioncriteria'] ?? [];
        } else {
            $manual = trim((string)($rules['manual'] ?? ''));
            $expected = json_decode($manual, true);
            if (!is_array($expected)) {
                $expected = [];
            }
        }
        $actual = $state['completioncriteria'] ?? [];
        if ($actual === $expected) {
            return;
        }
        $problems[] = self::problem(
            'completion',
            'BLUEPRINT_COURSE_COMPLETION',
            'warning',
            get_string('v_blueprint_course_completion', 'tool_coursecompliance'),
            self::describe_completion_criteria($expected),
            self::describe_completion_criteria($actual)
        );
    }

    /**
     * Compare named sections without requiring the same numeric position.
     *
     * @param array $problems Problem collector.
     * @param array $actual Actual sections.
     * @param array $expected Template sections.
     */
    private static function compare_sections(array &$problems, array $actual, array $expected): void {
        $actualnames = array_map(
            static fn ($section): string => \core_text::strtolower(trim((string)$section['name'])),
            $actual
        );
        foreach ($expected as $section) {
            $name = trim((string)$section['name']);
            if ($name === '' || in_array(\core_text::strtolower($name), $actualnames, true)) {
                continue;
            }
            $problems[] = self::problem(
                'course',
                'BLUEPRINT_SECTION_MISSING',
                'warning',
                get_string('v_blueprint_section_missing', 'tool_coursecompliance', $name),
                $name,
                get_string('missing', 'tool_coursecompliance')
            );
        }
    }

    /**
     * Compare accepted literature page names.
     *
     * @param array $problems Problem collector.
     * @param array $inventory Course inventory.
     * @param array $content Content rules.
     */
    private static function compare_literature_pages(array &$problems, array $inventory, array $content): void {
        $aliases = preg_split('/\R/u', (string)($content['literature_aliases'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $aliases = array_values(array_filter(array_map('trim', $aliases ?: [])));
        if (!$aliases) {
            return;
        }
        $pagenames = [];
        foreach ($inventory as $activity) {
            if ((string)$activity['modname'] !== 'page') {
                continue;
            }
            $pagenames[] = \core_text::strtolower(trim((string)$activity['name']));
        }
        $found = [];
        foreach ($aliases as $alias) {
            if (in_array(\core_text::strtolower($alias), $pagenames, true)) {
                $found[] = $alias;
            }
        }
        $mode = (string)($content['literature_mode'] ?? 'atleastone');
        $valid = $mode === 'all' ? count($found) === count($aliases) : count($found) > 0;
        if ($valid) {
            return;
        }
        $expected = $mode === 'all'
            ? implode(', ', $aliases)
            : get_string('oneofvalues', 'tool_coursecompliance', implode(', ', $aliases));
        $actual = $found ? implode(', ', $found) : get_string('missing', 'tool_coursecompliance');
        $problems[] = self::problem(
            'page',
            'BLUEPRINT_LITERATURE_PAGE_MISSING',
            'critical',
            get_string('v_blueprint_literature_page', 'tool_coursecompliance'),
            $expected,
            $actual
        );
    }

    /**
     * Compare a count rule.
     *
     * @param array $problems Problem collector.
     * @param string $rulecode Rule code.
     * @param string $component Component.
     * @param string $message Message.
     * @param string $mode Comparison mode.
     * @param int $manual Manual expected value.
     * @param int $template Template expected value.
     * @param int $actual Actual value.
     */
    private static function compare_count_rule(
        array &$problems,
        string $rulecode,
        string $component,
        string $message,
        string $mode,
        int $manual,
        int $template,
        int $actual
    ): void {
        if ($mode === 'ignore') {
            return;
        }
        $expected = $mode === 'template' ? $template : $manual;
        $valid = match ($mode) {
            'min' => $actual >= $expected,
            'max' => $actual <= $expected,
            default => $actual === $expected,
        };
        if ($valid) {
            return;
        }
        $expecteddisplay = match ($mode) {
            'min' => '>= ' . $expected,
            'max' => '<= ' . $expected,
            default => (string)$expected,
        };
        $problems[] = self::problem(
            $component,
            $rulecode,
            $actual < $expected ? 'warning' : 'notice',
            $message,
            $expecteddisplay,
            (string)$actual
        );
    }

    /**
     * Count regular quizzes excluding exam/resit targets.
     *
     * @param array $inventory Course activities.
     * @param string[] $targetids Reserved assessment idnumbers.
     * @return int
     */
    private static function count_ordinary_quizzes(array $inventory, array $targetids): int {
        $count = 0;
        foreach ($inventory as $activity) {
            if ((string)$activity['modname'] !== 'quiz') {
                continue;
            }
            if (in_array((string)$activity['idnumber'], $targetids, true)) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Count pages whose names contain or exactly match text.
     *
     * @param array $inventory Course inventory.
     * @param string $needle Search text.
     * @param bool $exact Exact match flag.
     * @return int
     */
    private static function count_named_pages(array $inventory, string $needle, bool $exact): int {
        $count = 0;
        $needle = \core_text::strtolower(trim($needle));
        foreach ($inventory as $activity) {
            if ((string)$activity['modname'] !== 'page') {
                continue;
            }
            $name = \core_text::strtolower(trim((string)$activity['name']));
            $match = $exact ? $name === $needle : \core_text::strpos($name, $needle) !== false;
            if ($match) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Legacy Blueprint comparison for profiles created before 0.6.
     *
     * @param \stdClass $course Course record.
     * @param array $state Loaded course state.
     * @param array $blueprint Stored Blueprint.
     * @param array $config Profile configuration.
     * @return array<int, array<string, mixed>>
     */
    private static function compare_legacy(\stdClass $course, array $state, array $blueprint, array $config): array {
        $problems = [];
        if (!empty($config['blueprintcheckcourse'])) {
            foreach (($blueprint['course'] ?? []) as $field => $expected) {
                $actual = $course->{$field} ?? null;
                if ((string)$actual === (string)$expected) {
                    continue;
                }
                $problems[] = self::problem(
                    'course',
                    'BLUEPRINT_COURSE_SETTING',
                    'warning',
                    get_string('v_blueprint_course_setting', 'tool_coursecompliance', $field),
                    (string)$expected,
                    (string)$actual
                );
            }
        }
        foreach (($blueprint['activities'] ?? []) as $idnumber => $expected) {
            $matches = $state['activities'][$idnumber] ?? [];
            if (!$matches) {
                $problems[] = self::problem(
                    (string)$expected['modname'],
                    'BLUEPRINT_ACTIVITY_MISSING',
                    'critical',
                    get_string('v_blueprint_activity_missing', 'tool_coursecompliance', $idnumber),
                    (string)$expected['modname'],
                    get_string('missing', 'tool_coursecompliance'),
                    0,
                    (string)($expected['name'] ?? '')
                );
                continue;
            }
            if (count($matches) > 1) {
                $problems[] = self::problem(
                    (string)$expected['modname'],
                    'BLUEPRINT_ACTIVITY_DUPLICATE',
                    'critical',
                    get_string('v_blueprint_activity_duplicate', 'tool_coursecompliance', $idnumber),
                    '1',
                    (string)count($matches),
                    (int)$matches[0]['instanceid'],
                    (string)$matches[0]['name']
                );
                continue;
            }
            $actual = $matches[0];
            if (
                !empty($config['blueprintcheckavailability'])
                && (string)$actual['availability'] !== (string)($expected['availability'] ?? '')
            ) {
                $problems[] = self::problem(
                    (string)$actual['modname'],
                    'BLUEPRINT_ACTIVITY_AVAILABILITY',
                    'warning',
                    get_string('v_blueprint_availability', 'tool_coursecompliance', $idnumber),
                    self::describe_availability((string)($expected['availability'] ?? '')),
                    self::describe_availability((string)$actual['availability']),
                    (int)$actual['instanceid'],
                    (string)$actual['name']
                );
            }
            if (
                !empty($config['blueprintcheckactivitycompletion'])
                && ($actual['completion'] ?? []) !== ($expected['completion'] ?? [])
            ) {
                $problems[] = self::problem(
                    (string)$actual['modname'],
                    'BLUEPRINT_ACTIVITY_COMPLETION',
                    'warning',
                    get_string('v_blueprint_activity_completion', 'tool_coursecompliance', $idnumber),
                    self::describe_completion($expected['completion'] ?? []),
                    self::describe_completion($actual['completion'] ?? []),
                    (int)$actual['instanceid'],
                    (string)$actual['name']
                );
            }
        }
        if (!empty($config['blueprintcheckgradebook'])) {
            foreach (($blueprint['gradeformulas'] ?? []) as $idnumber => $formula) {
                self::compare_formula(
                    $problems,
                    (string)$idnumber,
                    (string)$formula,
                    $state['gradeformulas'][$idnumber] ?? null
                );
            }
        }
        if (
            !empty($config['blueprintcheckcoursecompletion'])
            && ($state['completioncriteria'] ?? []) !== ($blueprint['completioncriteria'] ?? [])
        ) {
            $problems[] = self::problem(
                'completion',
                'BLUEPRINT_COURSE_COMPLETION',
                'warning',
                get_string('v_blueprint_course_completion', 'tool_coursecompliance'),
                self::describe_completion_criteria($blueprint['completioncriteria'] ?? []),
                self::describe_completion_criteria($state['completioncriteria'] ?? [])
            );
        }
        return $problems;
    }

    /**
     * Create a standard problem array.
     *
     * @param string $component Component.
     * @param string $rulecode Rule code.
     * @param string $severity Severity.
     * @param string $message Message.
     * @param string $expected Expected.
     * @param string $actual Actual.
     * @param int $objectid Related module instance id.
     * @param string $objectname Related object name.
     * @param string $assessmenttype Assessment type.
     * @return array<string, mixed>
     */
    private static function problem(
        string $component,
        string $rulecode,
        string $severity,
        string $message,
        string $expected,
        string $actual,
        int $objectid = 0,
        string $objectname = '',
        string $assessmenttype = 'course'
    ): array {
        return compact(
            'component',
            'rulecode',
            'severity',
            'message',
            'expected',
            'actual',
            'objectid',
            'objectname',
            'assessmenttype'
        );
    }

    /**
     * Create an assessment problem.
     *
     * @param array $context Assessment context.
     * @param string $rulecode Rule code.
     * @param string $message Message.
     * @param string $expected Expected value.
     * @param string $actual Actual value.
     * @return array<string, mixed>
     */
    private static function assessment_problem(
        array $context,
        string $rulecode,
        string $message,
        string $expected,
        string $actual
    ): array {
        return self::problem(
            'quiz',
            $rulecode,
            'warning',
            $message,
            $expected,
            $actual,
            (int)$context['objectid'],
            (string)$context['objectname'],
            (string)$context['type']
        );
    }

    /**
     * Keep only stable, unique idnumber activities in a template snapshot.
     *
     * @param array $activities Activity map.
     * @return array<string, array<string, mixed>>
     */
    private static function unique_blueprint_activities(array $activities): array {
        $result = [];
        foreach ($activities as $idnumber => $matches) {
            if (count($matches) !== 1) {
                continue;
            }
            $activity = $matches[0];
            unset($activity['cmid'], $activity['instanceid']);
            $result[$idnumber] = $activity;
        }
        return $result;
    }

    /**
     * Remove course-specific IDs from inventory before storing a snapshot.
     *
     * @param array $inventory Inventory.
     * @return array
     */
    private static function snapshot_inventory(array $inventory): array {
        foreach ($inventory as &$activity) {
            unset($activity['cmid'], $activity['instanceid']);
        }
        unset($activity);
        return $inventory;
    }

    /**
     * Load activity names for Moodle core modules used by the audit.
     *
     * @param array $instancesbymod Instance IDs by module.
     * @return array<string, array<int, string>>
     */
    private static function load_instance_names(array $instancesbymod): array {
        global $DB;

        $supported = ['quiz', 'page', 'resource', 'folder', 'url', 'book', 'assign', 'forum', 'label'];
        $names = [];
        foreach ($instancesbymod as $modname => $ids) {
            if (!in_array($modname, $supported, true) || !$ids) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal(array_unique($ids), SQL_PARAMS_NAMED, 'bpinst');
            $records = $DB->get_records_select($modname, "id $insql", $params, '', 'id,name');
            foreach ($records as $record) {
                $names[$modname][(int)$record->id] = (string)$record->name;
            }
        }
        return $names;
    }

    /**
     * Load Quiz settings and question statistics.
     *
     * @param int[] $courseids Course IDs.
     * @param string $courseinsql Course IN SQL.
     * @param array $courseparams Course parameters.
     * @param array $gradeitems Quiz grade item map.
     * @return array<int, array<string, mixed>>
     */
    private static function load_quiz_settings(
        array $courseids,
        string $courseinsql,
        array $courseparams,
        array $gradeitems
    ): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        $quizzes = $DB->get_records_select('quiz', "course $courseinsql", $courseparams);
        if (!$quizzes) {
            return [];
        }
        $quizids = array_keys($quizzes);
        [$quizinsql, $quizparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'bpquiz');
        $slotstats = $DB->get_records_sql(
            "SELECT quizid, COUNT(1) AS questioncount,
                    SUM(CASE WHEN maxmark <= 0 THEN 1 ELSE 0 END) AS zeromarkcount
               FROM {quiz_slots}
              WHERE quizid $quizinsql
           GROUP BY quizid",
            $quizparams
        );
        $out = [];
        foreach ($quizzes as $quiz) {
            $courseid = (int)$quiz->course;
            $gradeitem = $gradeitems[$courseid][(int)$quiz->id] ?? null;
            $stats = $slotstats[(int)$quiz->id] ?? null;
            $settings = [
                'gradepass' => $gradeitem ? (float)$gradeitem->gradepass : 0.0,
                'grade' => (float)$quiz->grade,
                'attempts' => (int)$quiz->attempts,
                'timelimit' => (int)$quiz->timelimit,
                'grademethod' => (int)$quiz->grademethod,
                'shuffleanswers' => (int)$quiz->shuffleanswers,
                'timeopen' => (int)$quiz->timeopen,
                'timeclose' => (int)$quiz->timeclose,
                'password' => (string)$quiz->password,
                'subnet' => (string)$quiz->subnet,
                'browsersecurity' => (string)$quiz->browsersecurity,
                'questioncount' => $stats ? (int)$stats->questioncount : 0,
                'zeromarkcount' => $stats ? (int)$stats->zeromarkcount : 0,
                'completionattemptsexhausted' => (int)($quiz->completionattemptsexhausted ?? 0),
                'completionminattempts' => (int)($quiz->completionminattempts ?? 0),
            ];
            foreach (self::REVIEW_FIELDS as $field) {
                $settings[$field] = (int)($quiz->{$field} ?? 0);
            }
            $out[(int)$quiz->id] = $settings;
        }
        return $out;
    }

    /**
     * Load stable group labels by course.
     *
     * @param int[] $courseids Course ids.
     * @return array<int, array<int, string>>
     */
    private static function load_group_maps(array $courseids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'bpgrp');
        $records = $DB->get_records_select('groups', "courseid $insql", $params);
        $maps = [];
        foreach ($records as $record) {
            $label = trim((string)$record->idnumber);
            if ($label === '') {
                $label = trim((string)$record->name);
            }
            $maps[(int)$record->courseid][(int)$record->id] = $label;
        }
        return $maps;
    }

    /**
     * Load stable grouping labels by course.
     *
     * @param int[] $courseids Course ids.
     * @return array<int, array<int, string>>
     */
    private static function load_grouping_maps(array $courseids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'bpgrouping');
        $records = $DB->get_records_select('groupings', "courseid $insql", $params);
        $maps = [];
        foreach ($records as $record) {
            $label = trim((string)$record->idnumber);
            if ($label === '') {
                $label = trim((string)$record->name);
            }
            $maps[(int)$record->courseid][(int)$record->id] = $label;
        }
        return $maps;
    }

    /**
     * Normalise availability JSON so cross-course IDs use stable labels.
     *
     * @param string $json Availability JSON.
     * @param array $cmidmap Course-module id to idnumber.
     * @param array $gradeidmap Grade-item id to idnumber.
     * @param array $groupidmap Group id to stable label.
     * @param array $groupingidmap Grouping id to stable label.
     * @return string
     */
    private static function normalise_availability(
        string $json,
        array $cmidmap,
        array $gradeidmap,
        array $groupidmap,
        array $groupingidmap
    ): string {
        if (trim($json) === '') {
            return '';
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        $walk = static function (&$value) use (
            &$walk,
            $cmidmap,
            $gradeidmap,
            $groupidmap,
            $groupingidmap
        ): void {
            if (!is_array($value)) {
                return;
            }
            if (($value['type'] ?? '') === 'completion' && isset($value['cm'])) {
                $value['cmidnumber'] = $cmidmap[(int)$value['cm']] ?? 'UNRESOLVED';
                unset($value['cm']);
            }
            if (($value['type'] ?? '') === 'grade' && isset($value['id'])) {
                $value['gradeidnumber'] = $gradeidmap[(int)$value['id']] ?? 'UNRESOLVED';
                unset($value['id']);
            }
            if (($value['type'] ?? '') === 'group' && isset($value['id'])) {
                $value['group'] = $groupidmap[(int)$value['id']] ?? 'UNRESOLVED';
                unset($value['id']);
            }
            if (($value['type'] ?? '') === 'grouping' && isset($value['id'])) {
                $value['grouping'] = $groupingidmap[(int)$value['id']] ?? 'UNRESOLVED';
                unset($value['id']);
            }
            foreach ($value as &$child) {
                $walk($child);
            }
            unset($child);
            if (!array_is_list($value)) {
                ksort($value);
            }
        };
        $walk($decoded);
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Normalise a grade calculation by replacing grade-item IDs with idnumbers.
     *
     * @param string $calculation Raw calculation.
     * @param array $gradeidmap Grade-item map.
     * @return string
     */
    private static function normalise_formula(string $calculation, array $gradeidmap): string {
        return preg_replace_callback(
            '/##gi(\d+)##/',
            static function (array $matches) use ($gradeidmap): string {
                $id = (int)$matches[1];
                $idnumber = trim((string)($gradeidmap[$id] ?? ''));
                return $idnumber !== '' ? '[[' . $idnumber . ']]' : '[[UNRESOLVED:' . $id . ']]';
            },
            $calculation
        ) ?? $calculation;
    }

    /**
     * Encode a visual review matrix into a Quiz bitmask.
     *
     * @param array $matrix Period values.
     * @return int
     */
    private static function encode_review_field(array $matrix): int {
        $value = 0;
        if (!empty($matrix['during'])) {
            $value |= self::REVIEW_DURING;
        }
        if (!empty($matrix['immediately'])) {
            $value |= self::REVIEW_IMMEDIATELY;
        }
        if (!empty($matrix['open'])) {
            $value |= self::REVIEW_OPEN;
        }
        if (!empty($matrix['closed'])) {
            $value |= self::REVIEW_CLOSED;
        }
        return $value;
    }

    /**
     * Describe Quiz review options for reports.
     *
     * @param array $values Review masks.
     * @return string
     */
    private static function describe_review(array $values): string {
        $periods = [
            'during' => self::REVIEW_DURING,
            'immediately' => self::REVIEW_IMMEDIATELY,
            'open' => self::REVIEW_OPEN,
            'closed' => self::REVIEW_CLOSED,
        ];
        $parts = [];
        foreach (self::REVIEW_FIELDS as $field) {
            $enabled = [];
            $mask = (int)($values[$field] ?? 0);
            foreach ($periods as $period => $bit) {
                if (($mask & $bit) !== 0) {
                    $enabled[] = get_string('reviewperiod_' . $period, 'tool_coursecompliance');
                }
            }
            $parts[] = get_string('reviewfield_' . $field, 'tool_coursecompliance') . ': ' .
                ($enabled ? implode(', ', $enabled) : get_string('none', 'tool_coursecompliance'));
        }
        return implode('; ', $parts);
    }

    /**
     * Describe normalised availability rules for reports.
     *
     * @param string $json Normalised JSON.
     * @return string
     */
    private static function describe_availability(string $json): string {
        if (trim($json) === '') {
            return get_string('none', 'tool_coursecompliance');
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        return self::describe_availability_node($decoded);
    }

    /**
     * Describe one availability tree node.
     *
     * @param array $node Node.
     * @return string
     */
    private static function describe_availability_node(array $node): string {
        if (isset($node['op'], $node['c']) && is_array($node['c'])) {
            $join = strtoupper((string)$node['op']) === '&' ? ' AND ' : ' OR ';
            $children = [];
            foreach ($node['c'] as $child) {
                $children[] = is_array($child) ? self::describe_availability_node($child) : (string)$child;
            }
            return '(' . implode($join, $children) . ')';
        }
        $type = (string)($node['type'] ?? 'condition');
        return match ($type) {
            'completion' => 'completion[' . (string)($node['cmidnumber'] ?? '?') . ']=' .
                (string)($node['e'] ?? '?'),
            'grade' => 'grade[' . (string)($node['gradeidnumber'] ?? '?') . '] ' .
                self::grade_condition($node),
            'date' => 'date ' . (string)($node['d'] ?? '') . ' ' .
                (!empty($node['t']) ? userdate((int)$node['t']) : '?'),
            'group' => 'group=' . (string)($node['group'] ?? '?'),
            'grouping' => 'grouping=' . (string)($node['grouping'] ?? '?'),
            default => $type . ': ' . (json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        };
    }

    /**
     * Describe a grade availability condition.
     *
     * @param array $node Grade node.
     * @return string
     */
    private static function grade_condition(array $node): string {
        $parts = [];
        if (isset($node['min'])) {
            $parts[] = '>= ' . $node['min'];
        }
        if (isset($node['max'])) {
            $parts[] = '< ' . $node['max'];
        }
        return $parts ? implode(' AND ', $parts) : get_string('configured', 'tool_coursecompliance');
    }

    /**
     * Describe activity completion values.
     *
     * @param array $completion Completion data.
     * @return string
     */
    private static function describe_completion(array $completion): string {
        $mode = (int)($completion['completion'] ?? 0);
        $modetext = match ($mode) {
            1 => get_string('completionmanual', 'tool_coursecompliance'),
            2 => get_string('completionautomatic', 'tool_coursecompliance'),
            default => get_string('disabled', 'tool_coursecompliance'),
        };
        $parts = [$modetext];
        if (!empty($completion['completionview'])) {
            $parts[] = get_string('completionrequireview', 'tool_coursecompliance');
        }
        if (!empty($completion['completionpassgrade'])) {
            $parts[] = get_string('completionrequirepassgrade', 'tool_coursecompliance');
        }
        if (($completion['completiongradeitemnumber'] ?? null) !== null) {
            $parts[] = get_string(
                'completiongradeitem',
                'tool_coursecompliance',
                (int)$completion['completiongradeitemnumber']
            );
        }
        if (!empty($completion['completionattemptsexhausted'])) {
            $parts[] = get_string('completionattemptsexhausted', 'tool_coursecompliance');
        }
        if (!empty($completion['completionminattempts'])) {
            $parts[] = get_string(
                'completionminattempts',
                'tool_coursecompliance',
                (int)$completion['completionminattempts']
            );
        }
        return implode('; ', $parts);
    }

    /**
     * Describe course completion criteria.
     *
     * @param array $criteria Criteria.
     * @return string
     */
    private static function describe_completion_criteria(array $criteria): string {
        if (!$criteria) {
            return get_string('none', 'tool_coursecompliance');
        }
        $parts = [];
        foreach ($criteria as $criterion) {
            if (!empty($criterion['moduleidnumber'])) {
                $parts[] = 'activity=' . $criterion['moduleidnumber'];
            } else if ($criterion['gradepass'] !== null) {
                $parts[] = 'course grade >= ' . self::format_float((float)$criterion['gradepass']);
            } else {
                $parts[] = 'criterion type=' . (int)($criterion['criteriatype'] ?? 0);
            }
        }
        return implode('; ', $parts);
    }

    /**
     * Display typed scalar value.
     *
     * @param mixed $value Value.
     * @param string $type Display type.
     * @return string
     */
    private static function display_typed($value, string $type): string {
        return match ($type) {
            'bool' => self::yesno((int)$value),
            'float' => self::format_float((float)$value),
            'duration' => (int)$value > 0 ? format_time((int)$value) : '0',
            'groupmode' => self::groupmode_name((int)$value),
            default => (string)$value,
        };
    }

    /**
     * Display a course scalar value.
     *
     * @param string $field Field name.
     * @param mixed $value Value.
     * @return string
     */
    private static function display_scalar(string $field, $value): string {
        return match ($field) {
            'showactivitydates', 'enablecompletion', 'groupmodeforce' => self::yesno((int)$value),
            'groupmode' => self::groupmode_name((int)$value),
            'maxbytes' => display_size((int)$value),
            default => (string)$value,
        };
    }

    /**
     * Human-readable group mode.
     *
     * @param int $value Group mode.
     * @return string
     */
    private static function groupmode_name(int $value): string {
        return match ($value) {
            1 => get_string('groupmodeseparate', 'tool_coursecompliance'),
            2 => get_string('groupmodevisible', 'tool_coursecompliance'),
            default => get_string('groupmodenone', 'tool_coursecompliance'),
        };
    }

    /**
     * Human-readable yes/no.
     *
     * @param int $value Value.
     * @return string
     */
    private static function yesno(int $value): string {
        return $value ? get_string('yes') : get_string('no');
    }

    /**
     * Describe configured-date presence.
     *
     * @param bool[] $pattern Open/close presence.
     * @return string
     */
    private static function describe_date_pattern(array $pattern): string {
        return get_string(
            'datepattern',
            'tool_coursecompliance',
            (object)[
                'open' => self::yesno(!empty($pattern[0]) ? 1 : 0),
                'close' => self::yesno(!empty($pattern[1]) ? 1 : 0),
            ]
        );
    }

    /**
     * Format a float without unnecessary trailing zeroes.
     *
     * @param float $value Value.
     * @return string
     */
    private static function format_float(float $value): string {
        return rtrim(rtrim(number_format($value, 5, '.', ''), '0'), '.');
    }

    /**
     * Recursively sort associative arrays.
     *
     * @param array $value Array to sort.
     */
    private static function recursive_ksort(array &$value): void {
        foreach ($value as &$child) {
            if (is_array($child)) {
                self::recursive_ksort($child);
            }
        }
        unset($child);
        if (!array_is_list($value)) {
            ksort($value);
        }
    }

    /**
     * Create an empty loaded state.
     *
     * @return array<string, mixed>
     */
    private static function empty_state(): array {
        return [
            'sections' => [],
            'activities' => [],
            'inventory' => [],
            'counts' => [],
            'gradeformulas' => [],
            'coursegrade' => [],
            'completioncriteria' => [],
        ];
    }
}
