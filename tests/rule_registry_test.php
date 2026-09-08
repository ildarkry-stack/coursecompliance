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

/**
 * Tests for built-in audit rules.
 *
 * @package    tool_coursecompliance
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_coursecompliance\rule_registry
 */
final class rule_registry_test extends \advanced_testcase {
    /**
     * Registry should include the expanded 0.4 audit set.
     */
    public function test_registry_contains_course_and_quiz_rules(): void {
        $this->assertGreaterThanOrEqual(4, count(rule_registry::course_rules()));
        $this->assertGreaterThanOrEqual(20, count(rule_registry::quiz_rules()));
    }

    /**
     * Completion rule detects disabled completion.
     */
    public function test_completion_rule_detects_disabled_completion(): void {
        $course = (object)['startdate' => 1, 'enddate' => 2, 'enablecompletion' => 0];
        $context = ['course' => $course, 'teachercount' => 1];
        $rule = $this->find_rule('COURSE_COMPLETION_ENABLED', rule_registry::course_rules());
        $this->assertNotNull($rule->evaluate($context, ['requirecompletion' => true]));
    }

    /**
     * Right answers visible while a quiz is open are a critical violation.
     */
    public function test_right_answers_while_open_are_detected(): void {
        $quiz = $this->quiz_record();
        $quiz->reviewrightanswer = 0x01000;
        $context = $this->quiz_context($quiz);
        $rule = $this->find_rule('QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN', rule_registry::quiz_rules());
        $this->assertNotNull($rule->evaluate($context, ['hideanswerswhileopen' => true]));
        $quiz->reviewrightanswer = 0x00010;
        $this->assertNull($rule->evaluate($this->quiz_context($quiz), ['hideanswerswhileopen' => true]));
    }

    /**
     * Zero-mark question slots are detected.
     */
    public function test_zero_mark_question_is_detected(): void {
        $context = $this->quiz_context($this->quiz_record());
        $context['zeromarkcount'] = 2;
        $rule = $this->find_rule('QUIZ_ZERO_MARK_QUESTIONS', rule_registry::quiz_rules());
        $this->assertNotNull($rule->evaluate($context, []));
    }

    /**
     * Oversized override attempt counts are detected.
     */
    public function test_override_attempt_limit_is_detected(): void {
        $context = $this->quiz_context($this->quiz_record());
        $context['maxoverrideattempts'] = 5;
        $rule = $this->find_rule('QUIZ_OVERRIDE_ATTEMPTS_LIMIT', rule_registry::quiz_rules());
        $this->assertNotNull($rule->evaluate($context, ['maxoverrideattempts' => 2]));
    }

    /**
     * Find one rule by code.
     *
     * @param string $code Rule code.
     * @param array $rules Rules.
     * @return \tool_coursecompliance\local\rule\rule
     */
    private function find_rule(string $code, array $rules): \tool_coursecompliance\local\rule\rule {
        foreach ($rules as $rule) {
            if ($rule->get_code() === $code) {
                return $rule;
            }
        }
        $this->fail('Rule not found: ' . $code);
    }

    /**
     * Build a representative Quiz record.
     */
    private function quiz_record(): \stdClass {
        return (object)[
            'id' => 10,
            'name' => 'Exam',
            'timeopen' => 100,
            'timeclose' => 200,
            'timelimit' => 3600,
            'attempts' => 1,
            'grade' => 100.0,
            'sumgrades' => 40.0,
            'reviewrightanswer' => 0,
            'reviewcorrectness' => 0,
            'password' => '',
            'subnet' => '',
            'browsersecurity' => '',
            'shuffleanswers' => 1,
        ];
    }

    /**
     * Build a representative Quiz rule context.
     */
    private function quiz_context(\stdClass $quiz): array {
        return [
            'quiz' => $quiz,
            'assessmenttype' => 'exam',
            'questioncount' => 40,
            'zeromarkcount' => 0,
            'gradepass' => 60.0,
            'gradeitemexists' => true,
            'gradeitemmax' => 100.0,
            'gradeitemlocked' => false,
            'overridecount' => 0,
            'maxoverrideattempts' => 0,
            'maxoverridetimeclose' => 0,
            'duplicatecount' => 1,
        ];
    }
}
