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
 * Helpers for human-readable compliance reports.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Builds administrator-friendly explanations for stored audit results.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_helper {
    /**
     * Determine whether the result describes a missing required object/value.
     *
     * @param \stdClass $result Stored result row.
     * @return bool
     */
    public static function is_missing(\stdClass $result): bool {
        if (str_ends_with((string)$result->rulecode, '_REQUIRED')) {
            return true;
        }
        $missingcodes = [
            'BLUEPRINT_ACTIVITY_MISSING',
            'BLUEPRINT_ASSESSMENT_MISSING',
            'BLUEPRINT_GRADE_FORMULA_MISSING',
            'BLUEPRINT_LITERATURE_PAGE_MISSING',
            'BLUEPRINT_SECTION_MISSING',
        ];
        if (in_array((string)$result->rulecode, $missingcodes, true)) {
            return true;
        }
        return in_array(
            (string)$result->rulecode,
            [
                'COURSE_STARTDATE_REQUIRED',
                'COURSE_TEACHER_REQUIRED',
                'QUIZ_OPEN_REQUIRED',
                'QUIZ_CLOSE_REQUIRED',
                'QUIZ_GRADEBOOK_MAX_MATCH',
                'QUIZ_GRADE_TO_PASS_REQUIRED',
                'QUIZ_PASSWORD_REQUIRED',
                'QUIZ_SUBNET_REQUIRED',
            ],
            true
        ) && self::looks_missing((string)$result->actual);
    }

    /**
     * Return a short administrator-friendly item description.
     *
     * @param \stdClass $result Stored result row.
     * @param array $config Profile configuration.
     * @return string
     */
    public static function item_label(\stdClass $result, array $config): string {
        $component = (string)$result->component;
        if ($component === 'course') {
            return get_string('courseconfiguration', 'tool_coursecompliance');
        }
        if ($component === 'gradebook') {
            return get_string('gradebook', 'grades');
        }
        if ($component === 'completion') {
            return get_string('completion', 'completion');
        }
        $type = (string)$result->assessmenttype;
        $idnumber = $config[$type . 'idnumber'] ?? '';
        $name = trim((string)($result->objectname ?? ''));
        $moduletype = $component === 'quiz' ? get_string('modulename', 'quiz') : $component;
        if ($name !== '') {
            return get_string('reportitemnamed', 'tool_coursecompliance', (object)[
                'type' => $moduletype,
                'name' => $name,
                'idnumber' => $idnumber,
            ]);
        }
        return get_string('reportitemmissing', 'tool_coursecompliance', (object)[
            'type' => $moduletype,
            'idnumber' => $idnumber,
        ]);
    }

    /**
     * Explain exactly what is missing for a result.
     *
     * @param \stdClass $result Stored result row.
     * @param array $config Profile configuration.
     * @return string
     */
    public static function missing_description(\stdClass $result, array $config): string {
        $type = (string)$result->assessmenttype;
        if (preg_match('/^QUIZ_(EXAM|RESIT1|RESIT2)_REQUIRED$/', (string)$result->rulecode)) {
            $idnumber = $config[$type . 'idnumber'] ?? $type;
            return get_string('missingquizbyidnumber', 'tool_coursecompliance', $idnumber);
        }
        return match ((string)$result->rulecode) {
            'BLUEPRINT_ACTIVITY_MISSING',
            'BLUEPRINT_ASSESSMENT_MISSING',
            'BLUEPRINT_GRADE_FORMULA_MISSING',
            'BLUEPRINT_LITERATURE_PAGE_MISSING',
            'BLUEPRINT_SECTION_MISSING' => (string)$result->message,
            'COURSE_STARTDATE_REQUIRED' => get_string('missingcoursestartdate', 'tool_coursecompliance'),
            'COURSE_TEACHER_REQUIRED' => get_string('missingteacher', 'tool_coursecompliance'),
            'QUIZ_OPEN_REQUIRED' => get_string('missingquizopen', 'tool_coursecompliance'),
            'QUIZ_CLOSE_REQUIRED' => get_string('missingquizclose', 'tool_coursecompliance'),
            'QUIZ_GRADE_TO_PASS_REQUIRED' => get_string('missinggradepass', 'tool_coursecompliance'),
            'QUIZ_PASSWORD_REQUIRED' => get_string('missingquizpassword', 'tool_coursecompliance'),
            'QUIZ_SUBNET_REQUIRED' => get_string('missingquizsubnet', 'tool_coursecompliance'),
            default => (string)$result->message,
        };
    }

    /**
     * Return an actionable remediation hint.
     *
     * @param \stdClass $result Stored result row.
     * @param array $config Profile configuration.
     * @return string
     */
    public static function recommendation(\stdClass $result, array $config): string {
        $type = (string)$result->assessmenttype;
        $idnumber = $config[$type . 'idnumber'] ?? $type;
        $rulecode = (string)$result->rulecode;
        if ($rulecode === 'BLUEPRINT_ASSESSMENT_MISSING') {
            return get_string('fixmissingquiz', 'tool_coursecompliance', $idnumber);
        }
        if (str_starts_with($rulecode, 'BLUEPRINT_ASSESSMENT_REVIEW')) {
            return get_string('fixreviewoptions', 'tool_coursecompliance');
        }
        if (str_starts_with($rulecode, 'BLUEPRINT_ASSESSMENT_AVAILABILITY')) {
            return get_string('fixblueprintavailability', 'tool_coursecompliance');
        }
        if (str_starts_with($rulecode, 'BLUEPRINT_ASSESSMENT_COMPLETION')) {
            return get_string('fixblueprintactivitycompletion', 'tool_coursecompliance');
        }
        if (str_starts_with($rulecode, 'BLUEPRINT_ASSESSMENT_')) {
            return get_string('fixassessmentsetting', 'tool_coursecompliance', $idnumber);
        }
        if (in_array($rulecode, ['BLUEPRINT_COURSE_GRADE_FORMULA', 'BLUEPRINT_COURSE_GRADE_MAX'], true)) {
            return get_string('fixblueprintgradeformula', 'tool_coursecompliance');
        }
        if (str_starts_with($rulecode, 'BLUEPRINT_COURSE_')) {
            return get_string('fixblueprintcoursesetting', 'tool_coursecompliance');
        }
        if (str_ends_with($rulecode, '_COUNT')) {
            return get_string('fixcontentcount', 'tool_coursecompliance');
        }
        return match ($rulecode) {
            'COURSE_STARTDATE_REQUIRED' => get_string('fixcoursestartdate', 'tool_coursecompliance'),
            'COURSE_DATES_VALID' => get_string('fixcoursedates', 'tool_coursecompliance'),
            'COURSE_COMPLETION_ENABLED',
            'COURSE_COMPLETION_CRITERIA_REQUIRED',
            'COURSE_COMPLETION_BROKEN_CRITERIA' => get_string('fixcoursecompletion', 'tool_coursecompliance'),
            'COURSE_TEACHER_REQUIRED' => get_string('fixcourseteacher', 'tool_coursecompliance'),
            'QUIZ_EXAM_REQUIRED',
            'QUIZ_RESIT1_REQUIRED',
            'QUIZ_RESIT2_REQUIRED' => get_string('fixmissingquiz', 'tool_coursecompliance', $idnumber),
            'QUIZ_TARGET_DUPLICATE' => get_string('fixduplicatetarget', 'tool_coursecompliance', $idnumber),
            'QUIZ_OPEN_REQUIRED',
            'QUIZ_CLOSE_REQUIRED',
            'QUIZ_DATES_VALID' => get_string('fixquizdates', 'tool_coursecompliance'),
            'QUIZ_ATTEMPTS_STANDARD' => get_string('fixquizattempts', 'tool_coursecompliance'),
            'QUIZ_TIMELIMIT_STANDARD' => get_string('fixquiztimelimit', 'tool_coursecompliance'),
            'QUIZ_GRADE_POSITIVE',
            'QUIZ_GRADE_STANDARD' => get_string('fixquizgrade', 'tool_coursecompliance'),
            'QUIZ_GRADEBOOK_MAX_MATCH',
            'QUIZ_GRADEITEM_REQUIRED',
            'QUIZ_GRADEITEM_HIDDEN',
            'GRADEBOOK_ORPHAN_QUIZ_ITEMS',
            'GRADEBOOK_DUPLICATE_QUIZ_ITEMS' => get_string('fixquizgradebook', 'tool_coursecompliance'),
            'QUIZ_GRADEITEM_LOCKED' => get_string('fixgradeitemlocked', 'tool_coursecompliance'),
            'QUIZ_QUESTIONS_REQUIRED',
            'QUIZ_QUESTION_COUNT_MIN',
            'QUIZ_QUESTION_COUNT_MAX',
            'QUIZ_ZERO_MARK_QUESTIONS',
            'QUIZ_SUMGRADES_POSITIVE',
            'QUIZ_BANK_DUPLICATE_CONTENT',
            'QUIZ_BANK_UNUSED_QUESTIONS',
            'QUIZ_STRUCTURE_DIFF_QUESTION_COUNT',
            'QUIZ_STRUCTURE_DIFF_GRADE',
            'QUIZ_STRUCTURE_DIFF_BANK_CATEGORY' => get_string('fixquizquestions', 'tool_coursecompliance'),
            'QUIZ_GRADE_TO_PASS_REQUIRED',
            'QUIZ_GRADE_TO_PASS_MIN',
            'QUIZ_GRADE_TO_PASS_VALID' => get_string('fixgradepass', 'tool_coursecompliance'),
            'QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN',
            'QUIZ_CORRECTNESS_HIDDEN_WHILE_OPEN' => get_string('fixreviewoptions', 'tool_coursecompliance'),
            'QUIZ_PASSWORD_REQUIRED',
            'QUIZ_SUBNET_REQUIRED',
            'QUIZ_BROWSER_SECURITY_STANDARD' => get_string('fixquizaccess', 'tool_coursecompliance'),
            'QUIZ_SHUFFLE_ANSWERS_REQUIRED' => get_string('fixshuffleanswers', 'tool_coursecompliance'),
            'QUIZ_OVERRIDES_PRESENT',
            'QUIZ_OVERRIDE_ATTEMPTS_LIMIT',
            'QUIZ_OVERRIDE_CLOSE_EXTENSION' => get_string('fixquizoverrides', 'tool_coursecompliance'),
            'BLUEPRINT_ACTIVITY_MISSING' => get_string('fixblueprintactivitymissing', 'tool_coursecompliance'),
            'BLUEPRINT_ACTIVITY_DUPLICATE' => get_string('fixblueprintactivityduplicate', 'tool_coursecompliance'),
            'BLUEPRINT_ACTIVITY_TYPE' => get_string('fixblueprintactivitytype', 'tool_coursecompliance'),
            'BLUEPRINT_ACTIVITY_AVAILABILITY' => get_string('fixblueprintavailability', 'tool_coursecompliance'),
            'BLUEPRINT_ACTIVITY_COMPLETION' => get_string('fixblueprintactivitycompletion', 'tool_coursecompliance'),
            'BLUEPRINT_COURSE_SETTING' => get_string('fixblueprintcoursesetting', 'tool_coursecompliance'),
            'BLUEPRINT_SECTION_MISSING' => get_string('fixblueprintsection', 'tool_coursecompliance'),
            'BLUEPRINT_GRADE_FORMULA',
            'BLUEPRINT_GRADE_FORMULA_MISSING' => get_string('fixblueprintgradeformula', 'tool_coursecompliance'),
            'BLUEPRINT_COURSE_COMPLETION' => get_string('fixblueprintcoursecompletion', 'tool_coursecompliance'),
            'BLUEPRINT_DUPLICATE_IDNUMBER' => get_string('fixduplicateidnumbers', 'tool_coursecompliance'),
            'BLUEPRINT_LITERATURE_PAGE_MISSING' => get_string('fixliteraturepage', 'tool_coursecompliance'),
            default => get_string('reviewmanually', 'tool_coursecompliance'),
        };
    }

    /**
     * Build a direct Moodle edit link where possible.
     *
     * @param \stdClass $result Result row including optional cmid.
     * @return \moodle_url
     */
    public static function edit_url(\stdClass $result): \moodle_url {
        if (!empty($result->cmid)) {
            return new \moodle_url('/course/modedit.php', ['update' => (int)$result->cmid, 'return' => 1]);
        }
        if ((string)$result->component === 'gradebook') {
            return new \moodle_url('/grade/edit/tree/index.php', ['id' => (int)$result->courseid]);
        }
        if ((string)$result->component === 'completion') {
            return new \moodle_url('/course/completion.php', ['id' => (int)$result->courseid]);
        }
        return new \moodle_url('/course/view.php', ['id' => (int)$result->courseid]);
    }

    /**
     * Build a concise location description.
     *
     * @param \stdClass $result Stored result row.
     * @param array $config Profile configuration.
     * @return string
     */
    public static function location(\stdClass $result, array $config): string {
        $component = (string)$result->component;
        if ($component === 'course') {
            return get_string('courselevel', 'tool_coursecompliance');
        }
        if ($component === 'gradebook') {
            return get_string('gradebooklocation', 'tool_coursecompliance');
        }
        if ($component === 'completion') {
            return get_string('coursecompletionlocation', 'tool_coursecompliance');
        }
        $type = (string)$result->assessmenttype;
        $idnumber = $config[$type . 'idnumber'] ?? '';
        $name = trim((string)($result->objectname ?? ''));
        if ($type === 'course') {
            return $name !== '' ? $component . ': ' . $name : $component;
        }
        if ($name === '') {
            return get_string('expectedquizlocation', 'tool_coursecompliance', $idnumber);
        }
        return get_string('quizlocation', 'tool_coursecompliance', (object)[
            'name' => $name,
            'idnumber' => $idnumber,
        ]);
    }

    /**
     * Detect common textual representations of a missing value.
     *
     * @param string $actual Actual value.
     * @return bool
     */
    private static function looks_missing(string $actual): bool {
        $actual = trim(\core_text::strtolower($actual));
        return $actual === '' || $actual === '0' || $actual === 'missing' || $actual === 'отсутствует';
    }
}
