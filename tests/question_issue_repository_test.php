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
 * Tests for detailed question issue mapping.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Question issue repository tests.
 *
 * @covers \tool_coursecompliance\question_issue_repository
 */
final class question_issue_repository_test extends \advanced_testcase {
    /**
     * Aggregate correct-answer rule exposes its detailed issue code.
     */
    public function test_correct_answer_rule_maps_to_question_detail(): void {
        $this->assertTrue(question_issue_repository::supports_rule('QUIZ_QUESTION_CORRECT_ANSWER_REQUIRED'));
        $this->assertSame(
            ['no_correct_answer'],
            question_issue_repository::issue_codes_for_rule('QUIZ_QUESTION_CORRECT_ANSWER_REQUIRED')
        );
    }

    /**
     * Unrelated rules do not advertise question-level details.
     */
    public function test_unrelated_rule_has_no_question_detail(): void {
        $this->assertFalse(question_issue_repository::supports_rule('COURSE_STARTDATE_REQUIRED'));
        $this->assertSame([], question_issue_repository::issue_codes_for_rule('COURSE_STARTDATE_REQUIRED'));
    }
}
