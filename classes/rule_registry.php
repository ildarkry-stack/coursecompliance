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

namespace tool_coursecompliance;

use tool_coursecompliance\local\rule\callback_rule;
use tool_coursecompliance\local\rule\violation;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry of built-in audit rules.
 *
 * @package    tool_coursecompliance
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule_registry {
    /**
     * Get course-level rules.
     *
     * @return array<int, \tool_coursecompliance\local\rule\rule>
     */
    public static function course_rules(): array {
        return [
            new callback_rule('COURSE_STARTDATE_REQUIRED', 'course', 'warning', static function(array $ctx): ?violation {
                if (!empty($ctx['course']->startdate)) {
                    return null;
                }
                return new violation(
                    get_string('v_course_startdate', 'tool_coursecompliance'),
                    get_string('configured', 'tool_coursecompliance'),
                    get_string('missing', 'tool_coursecompliance')
                );
            }),
            new callback_rule('COURSE_DATES_VALID', 'course', 'critical', static function(array $ctx): ?violation {
                $course = $ctx['course'];
                if (!empty($course->enddate) && !empty($course->startdate) && $course->enddate <= $course->startdate) {
                    return new violation(
                        get_string('v_course_dates', 'tool_coursecompliance'),
                        'enddate > startdate',
                        userdate($course->enddate)
                    );
                }
                return null;
            }),
            new callback_rule('COURSE_COMPLETION_ENABLED', 'course', 'warning', static function(array $ctx, array $config): ?violation {
                if (empty($config['requirecompletion']) || !empty($ctx['course']->enablecompletion)) {
                    return null;
                }
                return new violation(
                    get_string('v_course_completion', 'tool_coursecompliance'),
                    get_string('enabled', 'tool_coursecompliance'),
                    get_string('disabled', 'tool_coursecompliance')
                );
            }),
            new callback_rule('COURSE_TEACHER_REQUIRED', 'course', 'critical', static function(array $ctx, array $config): ?violation {
                if (empty($config['requireteacher']) || !empty($ctx['teachercount'])) {
                    return null;
                }
                return new violation(get_string('v_course_teacher', 'tool_coursecompliance'), '>= 1', '0');
            }),
        ];
    }

    /**
     * Get quiz assessment rules.
     *
     * @return array<int, \tool_coursecompliance\local\rule\rule>
     */
    public static function quiz_rules(): array {
        return [
            new callback_rule('QUIZ_TARGET_DUPLICATE', 'quiz', 'critical', static function(array $ctx): ?violation {
                if ((int)($ctx['duplicatecount'] ?? 1) <= 1) {
                    return null;
                }
                return self::quiz_violation(
                    $ctx['quiz'],
                    'v_quiz_duplicate_target',
                    '1',
                    (string)$ctx['duplicatecount']
                );
            }),
            new callback_rule('QUIZ_OPEN_REQUIRED', 'quiz', 'warning', static function(array $ctx): ?violation {
                $quiz = $ctx['quiz'];
                if (!empty($quiz->timeopen)) {
                    return null;
                }
                return self::quiz_violation($quiz, 'v_quiz_open', get_string('configured', 'tool_coursecompliance'), get_string('missing', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_CLOSE_REQUIRED', 'quiz', 'critical', static function(array $ctx): ?violation {
                $quiz = $ctx['quiz'];
                if (!empty($quiz->timeclose)) {
                    return null;
                }
                return self::quiz_violation($quiz, 'v_quiz_close', get_string('configured', 'tool_coursecompliance'), get_string('missing', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_DATES_VALID', 'quiz', 'critical', static function(array $ctx): ?violation {
                $quiz = $ctx['quiz'];
                if (!empty($quiz->timeopen) && !empty($quiz->timeclose) && $quiz->timeclose <= $quiz->timeopen) {
                    return self::quiz_violation($quiz, 'v_quiz_dates', 'timeclose > timeopen', userdate($quiz->timeclose));
                }
                return null;
            }),
            new callback_rule('QUIZ_ATTEMPTS_STANDARD', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $expected = (int)($config['examattempts'] ?? 0);
                if ($expected <= 0) {
                    return null;
                }
                $quiz = $ctx['quiz'];
                if ((int)$quiz->attempts === $expected) {
                    return null;
                }
                $actual = empty($quiz->attempts) ? get_string('unlimited', 'tool_coursecompliance') : (string)$quiz->attempts;
                return self::quiz_violation($quiz, 'v_quiz_attempts', (string)$expected, $actual);
            }),
            new callback_rule('QUIZ_TIMELIMIT_STANDARD', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $expected = (int)($config['examtimelimit'] ?? 0);
                if ($expected <= 0) {
                    return null;
                }
                $quiz = $ctx['quiz'];
                if ((int)$quiz->timelimit === $expected) {
                    return null;
                }
                return self::quiz_violation($quiz, 'v_quiz_timelimit_standard', format_time($expected), format_time((int)$quiz->timelimit));
            }),
            new callback_rule('QUIZ_GRADE_POSITIVE', 'quiz', 'critical', static function(array $ctx): ?violation {
                $quiz = $ctx['quiz'];
                if ((float)$quiz->grade > 0) {
                    return null;
                }
                return self::quiz_violation($quiz, 'v_quiz_grade', '> 0', (string)$quiz->grade);
            }),
            new callback_rule('QUIZ_GRADE_STANDARD', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $expected = (float)($config['examgrade'] ?? 0);
                if ($expected <= 0) {
                    return null;
                }
                $quiz = $ctx['quiz'];
                if (abs((float)$quiz->grade - $expected) < 0.00001) {
                    return null;
                }
                return self::quiz_violation($quiz, 'v_quiz_grade_standard', (string)$expected, (string)$quiz->grade);
            }),
            new callback_rule('QUIZ_GRADEBOOK_MAX_MATCH', 'quiz', 'critical', static function(array $ctx): ?violation {
                $quiz = $ctx['quiz'];
                if (empty($ctx['gradeitemexists'])) {
                    return self::quiz_violation($quiz, 'v_quiz_gradeitem_missing', get_string('present', 'tool_coursecompliance'), get_string('missing', 'tool_coursecompliance'));
                }
                if (abs((float)$ctx['gradeitemmax'] - (float)$quiz->grade) < 0.00001) {
                    return null;
                }
                return self::quiz_violation($quiz, 'v_quiz_gradebook_max', (string)$quiz->grade, (string)$ctx['gradeitemmax']);
            }),
            new callback_rule('QUIZ_GRADEITEM_LOCKED', 'quiz', 'warning', static function(array $ctx): ?violation {
                if (empty($ctx['gradeitemlocked'])) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_gradeitem_locked', get_string('unlocked', 'tool_coursecompliance'), get_string('locked', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_QUESTIONS_REQUIRED', 'quiz', 'critical', static function(array $ctx): ?violation {
                if (!empty($ctx['questioncount'])) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_questions', '>= 1', '0');
            }),
            new callback_rule('QUIZ_QUESTION_COUNT_MIN', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $minimum = (int)($config['minquestions'] ?? 0);
                if ($minimum <= 0 || (int)$ctx['questioncount'] >= $minimum) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_questions_min', '>= ' . $minimum, (string)$ctx['questioncount']);
            }),
            new callback_rule('QUIZ_QUESTION_COUNT_MAX', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $maximum = (int)($config['maxquestions'] ?? 0);
                if ($maximum <= 0 || (int)$ctx['questioncount'] <= $maximum) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_questions_max', '<= ' . $maximum, (string)$ctx['questioncount']);
            }),
            new callback_rule('QUIZ_ZERO_MARK_QUESTIONS', 'quiz', 'critical', static function(array $ctx): ?violation {
                if (empty($ctx['zeromarkcount'])) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_zero_marks', '0', (string)$ctx['zeromarkcount']);
            }),
            new callback_rule('QUIZ_SUMGRADES_POSITIVE', 'quiz', 'critical', static function(array $ctx): ?violation {
                if ((float)$ctx['quiz']->sumgrades > 0 || empty($ctx['questioncount'])) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_sumgrades', '> 0', (string)$ctx['quiz']->sumgrades);
            }),
            new callback_rule('QUIZ_GRADE_TO_PASS_REQUIRED', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (empty($config['requiregradepass']) || ($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                if ((float)$ctx['gradepass'] > 0) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_gradepass', '> 0', '0');
            }),
            new callback_rule('QUIZ_GRADE_TO_PASS_MIN', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $minimum = (float)($config['mingradepass'] ?? 0);
                if ($minimum <= 0 || (float)$ctx['gradepass'] >= $minimum) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_gradepass_min', '>= ' . $minimum, (string)$ctx['gradepass']);
            }),
            new callback_rule('QUIZ_GRADE_TO_PASS_VALID', 'quiz', 'critical', static function(array $ctx): ?violation {
                if ((float)$ctx['gradepass'] <= (float)$ctx['quiz']->grade) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_gradepass_invalid', '<= ' . $ctx['quiz']->grade, (string)$ctx['gradepass']);
            }),
            new callback_rule('QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN', 'quiz', 'critical', static function(array $ctx, array $config): ?violation {
                if (empty($config['hideanswerswhileopen'])) {
                    return null;
                }
                $mask = 0x01000 | 0x00100;
                if (((int)$ctx['quiz']->reviewrightanswer & $mask) === 0) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_rightanswer_open', get_string('disabled', 'tool_coursecompliance'), get_string('enabled', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_CORRECTNESS_HIDDEN_WHILE_OPEN', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (empty($config['hidecorrectnesswhileopen'])) {
                    return null;
                }
                $mask = 0x01000 | 0x00100;
                if (((int)$ctx['quiz']->reviewcorrectness & $mask) === 0) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_correctness_open', get_string('disabled', 'tool_coursecompliance'), get_string('enabled', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_PASSWORD_REQUIRED', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (empty($config['requirepassword']) || ($ctx['assessmenttype'] ?? '') !== 'exam' || trim((string)$ctx['quiz']->password) !== '') {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_password', get_string('configured', 'tool_coursecompliance'), get_string('missing', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_SUBNET_REQUIRED', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (empty($config['requiresubnet']) || ($ctx['assessmenttype'] ?? '') !== 'exam' || trim((string)$ctx['quiz']->subnet) !== '') {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_subnet', get_string('configured', 'tool_coursecompliance'), get_string('missing', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_BROWSER_SECURITY_STANDARD', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (($ctx['assessmenttype'] ?? '') !== 'exam') {
                    return null;
                }
                $expected = trim((string)($config['expectedbrowsersecurity'] ?? ''));
                if ($expected === '' || (string)$ctx['quiz']->browsersecurity === $expected) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_browsersecurity', $expected, (string)$ctx['quiz']->browsersecurity);
            }),
            new callback_rule('QUIZ_SHUFFLE_ANSWERS_REQUIRED', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                if (empty($config['requireshuffleanswers']) || ($ctx['assessmenttype'] ?? '') !== 'exam' || !empty($ctx['quiz']->shuffleanswers)) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_shuffleanswers', get_string('enabled', 'tool_coursecompliance'), get_string('disabled', 'tool_coursecompliance'));
            }),
            new callback_rule('QUIZ_OVERRIDES_PRESENT', 'quiz', 'notice', static function(array $ctx, array $config): ?violation {
                if (empty($config['warnoverrides']) || empty($ctx['overridecount'])) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_overrides', '0', (string)$ctx['overridecount']);
            }),
            new callback_rule('QUIZ_OVERRIDE_ATTEMPTS_LIMIT', 'quiz', 'critical', static function(array $ctx, array $config): ?violation {
                $maximum = (int)($config['maxoverrideattempts'] ?? 0);
                if ($maximum <= 0 || (int)$ctx['maxoverrideattempts'] <= $maximum) {
                    return null;
                }
                return self::quiz_violation($ctx['quiz'], 'v_quiz_override_attempts', '<= ' . $maximum, (string)$ctx['maxoverrideattempts']);
            }),
            new callback_rule('QUIZ_OVERRIDE_CLOSE_EXTENSION', 'quiz', 'warning', static function(array $ctx, array $config): ?violation {
                $allowed = (int)($config['maxoverrideextension'] ?? 0);
                $timeclose = (int)$ctx['quiz']->timeclose;
                if ($allowed <= 0 || $timeclose <= 0 || (int)$ctx['maxoverridetimeclose'] <= $timeclose + $allowed) {
                    return null;
                }
                $extension = (int)$ctx['maxoverridetimeclose'] - $timeclose;
                return self::quiz_violation($ctx['quiz'], 'v_quiz_override_close', '<= ' . format_time($allowed), format_time($extension));
            }),
        ];
    }

    /**
     * Build a violation linked to a quiz.
     *
     * @param \stdClass $quiz Quiz record.
     * @param string $stringkey Language string key.
     * @param string $expected Expected value.
     * @param string $actual Actual value.
     * @return violation
     */
    private static function quiz_violation(\stdClass $quiz, string $stringkey, string $expected, string $actual): violation {
        return new violation(
            get_string($stringkey, 'tool_coursecompliance'),
            $expected,
            $actual,
            (int)$quiz->id,
            (string)$quiz->name
        );
    }
}
