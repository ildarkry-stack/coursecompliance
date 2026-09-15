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
 * Tests for deterministic question-answer validation.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Quiz integrity inspector tests.
 *
 * @covers \tool_coursecompliance\quiz_integrity_inspector
 */
final class quiz_integrity_inspector_test extends \advanced_testcase {
    /**
     * A real Moodle Quiz with standard multichoice, matching and short-answer questions is valid.
     */
    public function test_real_quiz_standard_question_types_do_not_raise_false_answer_errors(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'grade' => 3.0,
            'sumgrades' => 3.0,
        ]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $multichoice = $questiongenerator->create_question(
            'multichoice',
            'one_of_four',
            ['category' => $category->id]
        );
        $matching = $questiongenerator->create_question('match', null, ['category' => $category->id]);
        $shortanswer = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);

        quiz_add_quiz_question($multichoice->id, $quiz, 0, 1);
        quiz_add_quiz_question($matching->id, $quiz, 0, 1);
        quiz_add_quiz_question($shortanswer->id, $quiz, 0, 1);

        $result = quiz_integrity_inspector::inspect($quiz);
        $this->assertSame(0, (int)$result['nocorrectanswercount']);
        $this->assertSame(0, (int)$result['brokenquestioncount']);
        $this->assertSame('', (string)$result['structureerror']);
    }

    /**
     * A normal single-answer multichoice question is accepted without attempt state.
     */
    public function test_single_multichoice_with_one_full_credit_answer_is_valid(): void {
        $this->set_static_property('answercache', [
            10 => [
                (object)['answer' => 'Correct', 'fraction' => 1.0],
                (object)['answer' => 'Wrong 1', 'fraction' => 0.0],
                (object)['answer' => 'Wrong 2', 'fraction' => 0.0],
                (object)['answer' => 'Wrong 3', 'fraction' => 0.0],
            ],
        ]);
        $this->set_static_property('multichoicecache', [
            10 => (object)['single' => 1],
        ]);

        $result = $this->invoke_validator('validate_multichoice_answer', [10]);
        $this->assertSame('valid', $result['status']);
    }

    /**
     * A normal matching question with complete pairs is accepted without attempt state.
     */
    public function test_matching_with_complete_pairs_is_valid(): void {
        $this->set_static_property('matchcache', [
            20 => [
                (object)['questiontext' => 'A', 'answertext' => '1'],
                (object)['questiontext' => 'B', 'answertext' => '2'],
                (object)['questiontext' => 'C', 'answertext' => '3'],
                (object)['questiontext' => 'D', 'answertext' => '4'],
            ],
        ]);

        $result = $this->invoke_validator('validate_match_answer', [20]);
        $this->assertSame('valid', $result['status']);
    }

    /**
     * Set private static inspector cache for deterministic unit testing.
     *
     * @param string $name Property name.
     * @param array $value Value.
     */
    private function set_static_property(string $name, array $value): void {
        $property = new \ReflectionProperty(quiz_integrity_inspector::class, $name);
        $property->setValue(null, $value);
    }

    /**
     * Invoke a private static validator.
     *
     * @param string $method Method name.
     * @param array $arguments Arguments.
     * @return array
     */
    private function invoke_validator(string $method, array $arguments): array {
        $reflection = new \ReflectionMethod(quiz_integrity_inspector::class, $method);
        return $reflection->invokeArgs(null, $arguments);
    }
}
