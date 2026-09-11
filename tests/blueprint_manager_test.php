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
 * Tests for course blueprint comparison.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Blueprint comparison tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursecompliance\blueprint_manager
 */
final class blueprint_manager_test extends \advanced_testcase {
    /**
     * Missing template activity is reported with its idnumber.
     */
    public function test_missing_activity_is_reported(): void {
        $course = (object)[
            'format' => 'topics',
            'enablecompletion' => 1,
            'groupmode' => 0,
            'groupmodeforce' => 0,
        ];
        $state = [
            'sections' => [],
            'activities' => [],
            'gradeformulas' => [],
            'completioncriteria' => [],
        ];
        $blueprint = [
            'course' => [],
            'sections' => [],
            'activities' => [
                'exam' => [
                    'modname' => 'quiz',
                    'name' => 'Exam',
                    'availability' => '',
                    'completion' => [],
                ],
            ],
            'gradeformulas' => [],
            'completioncriteria' => [],
        ];
        $problems = blueprint_manager::compare($course, $state, $blueprint, profile_config::defaults());
        $this->assertCount(1, $problems);
        $this->assertSame('BLUEPRINT_ACTIVITY_MISSING', $problems[0]['rulecode']);
        $this->assertSame('quiz', $problems[0]['component']);
    }

    /**
     * Course-level drift is detected when template comparison is enabled.
     */
    public function test_course_setting_drift_is_reported(): void {
        $course = (object)[
            'format' => 'weeks',
            'enablecompletion' => 1,
            'groupmode' => 0,
            'groupmodeforce' => 0,
        ];
        $state = [
            'sections' => [],
            'activities' => [],
            'gradeformulas' => [],
            'completioncriteria' => [],
        ];
        $blueprint = [
            'course' => ['format' => 'topics'],
            'sections' => [],
            'activities' => [],
            'gradeformulas' => [],
            'completioncriteria' => [],
        ];
        $config = profile_config::defaults();
        $config['blueprintcheckcourse'] = true;
        $problems = blueprint_manager::compare($course, $state, $blueprint, $config);
        $this->assertCount(1, $problems);
        $this->assertSame('BLUEPRINT_COURSE_SETTING', $problems[0]['rulecode']);
        $this->assertSame('topics', $problems[0]['expected']);
        $this->assertSame('weeks', $problems[0]['actual']);
    }
}
