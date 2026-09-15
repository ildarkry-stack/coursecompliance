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
 * Cross-version API compatibility tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Verify that APIs required by CourseCompliance exist on every supported Moodle branch.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class compatibility_test extends \advanced_testcase {
    /**
     * Core output hooks used by the course indicator must exist.
     */
    public function test_required_output_hook_api_exists(): void {
        $this->assertTrue(class_exists(\core\hook\output\before_standard_top_of_body_html_generation::class));
        $this->assertTrue(class_exists(\core_availability\frontend::class));
    }

    /**
     * Question-bank APIs used by the deep Quiz audit must exist.
     */
    public function test_required_question_bank_apis_exist(): void {
        $this->assertTrue(class_exists(\mod_quiz\question\bank\qbank_helper::class));
        $this->assertTrue(class_exists(\core_question\local\bank\random_question_loader::class));
        $this->assertTrue(class_exists(\core_question\local\bank\question_bank_helper::class));
    }

    /**
     * Quiz APIs used by safe remediation must exist.
     */
    public function test_required_quiz_remediation_apis_exist(): void {
        $this->assertTrue(class_exists(\mod_quiz\quiz_settings::class));
        $this->assertTrue(class_exists(\mod_quiz\grade_calculator::class));
        $this->assertTrue(method_exists(\mod_quiz\grade_calculator::class, 'update_quiz_maximum_grade'));
    }

    /**
     * Moodle task and messaging APIs used by large scans must exist.
     */
    public function test_required_background_processing_apis_exist(): void {
        $this->assertTrue(class_exists(\core\task\adhoc_task::class));
        $this->assertTrue(class_exists(\core\task\manager::class));
        $this->assertTrue(class_exists(\core\message\message::class));
    }
}
