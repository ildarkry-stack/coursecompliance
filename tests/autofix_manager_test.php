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
 * Tests for safe automatic remediation planning.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Tests for safe automatic remediation planning.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursecompliance\autofix_manager
 */
final class autofix_manager_test extends \advanced_testcase {
    /**
     * A standard attempts mismatch is fixable only when the profile has a target.
     */
    public function test_attempts_rule_requires_explicit_target(): void {
        $result = (object)[
            'component' => 'quiz',
            'objectid' => 10,
            'rulecode' => 'QUIZ_ATTEMPTS_STANDARD',
            'assessmenttype' => 'exam',
        ];
        $config = profile_config::defaults();
        $this->assertTrue(autofix_manager::is_fixable($result, $config));

        $config['examattempts'] = 0;
        $this->assertFalse(autofix_manager::is_fixable($result, $config));
    }

    /**
     * Grade-to-pass warnings are fixable only when the profile has an explicit target.
     */
    public function test_gradepass_rule_requires_explicit_target(): void {
        $result = (object)[
            'component' => 'quiz',
            'objectid' => 10,
            'rulecode' => 'QUIZ_GRADE_TO_PASS_MIN',
            'assessmenttype' => 'exam',
        ];
        $config = profile_config::defaults();
        $config['mingradepass'] = 60.0;
        $this->assertTrue(autofix_manager::is_fixable($result, $config));

        $config['mingradepass'] = 0;
        $this->assertFalse(autofix_manager::is_fixable($result, $config));
    }

    /**
     * Hiding the right answer preserves unrelated review periods.
     */
    public function test_review_fix_clears_only_open_periods(): void {
        $result = (object)[
            'component' => 'quiz',
            'objectid' => 10,
            'rulecode' => 'QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN',
            'assessmenttype' => 'exam',
        ];
        $quiz = (object)[
            'reviewrightanswer' => 0x10000 | 0x1000 | 0x100 | 0x10,
        ];
        $cm = (object)[
            'visible' => 1,
            'visibleoncoursepage' => 1,
        ];
        $plan = (new autofix_manager())->plan_result($result, profile_config::defaults(), $quiz, $cm);
        $this->assertSame(0x10000 | 0x10, $plan['reviewrightanswer']);
    }

    /**
     * Unsafe content-level problems are never advertised as auto-fixable.
     */
    public function test_missing_quiz_is_not_fixable(): void {
        $result = (object)[
            'component' => 'quiz',
            'objectid' => 0,
            'rulecode' => 'QUIZ_EXAM_REQUIRED',
            'assessmenttype' => 'exam',
        ];
        $this->assertFalse(autofix_manager::is_fixable($result, profile_config::defaults()));
    }

    /**
     * A prepared fix uses the normal activity batch engine and changes the Quiz setting safely.
     */
    public function test_prepare_and_apply_attempts_fix(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Exam',
            'cmidnumber' => 'exam',
            'attempts' => 2,
        ]);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        $cm = $DB->get_record('course_modules', [
            'course' => $course->id,
            'module' => $moduleid,
            'idnumber' => 'exam',
        ], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        $config = profile_config::defaults();
        $config['examattempts'] = 1;
        $now = time();
        $profileid = $DB->insert_record('tool_coursecompliance_profile', (object)[
            'name' => 'Auto-fix test',
            'description' => '',
            'enabled' => 1,
            'configjson' => profile_config::encode($config),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $scanid = $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => $category->id,
            'userid' => $USER->id,
            'status' => 'complete',
            'courses' => 1,
            'assessments' => 1,
            'critical' => 0,
            'warnings' => 1,
            'notices' => 0,
            'ignored' => 0,
            'startedat' => $now,
            'finishedat' => $now,
            'errormessage' => '',
            'timecreated' => $now,
        ]);
        $DB->insert_record('tool_coursecompliance_result', (object)[
            'scanid' => $scanid,
            'courseid' => $course->id,
            'component' => 'quiz',
            'assessmenttype' => 'exam',
            'rulecode' => 'QUIZ_ATTEMPTS_STANDARD',
            'severity' => 'warning',
            'objectid' => $quiz->id,
            'objectname' => $quiz->name,
            'expected' => '1',
            'actual' => '2',
            'message' => 'Attempts mismatch',
            'timecreated' => $now,
        ]);

        $batchid = (new autofix_manager())->prepare_scan($scanid, $USER->id);
        $batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid], '*', MUST_EXIST);
        $this->assertSame('preview', $batch->status);
        $this->assertSame('autofix_scan', $batch->action);
        $this->assertSame(1, (int)$batch->totalchanges);

        $change = $DB->get_record('tool_coursecompliance_act_change', ['batchid' => $batchid], '*', MUST_EXIST);
        $this->assertSame('attempts', $change->fieldname);
        $this->assertSame('2', $change->oldvalue);
        $this->assertSame('1', $change->newvalue);

        $DB->set_field('tool_coursecompliance_act_batch', 'status', 'queued', ['id' => $batchid]);
        (new activity_manager())->apply_batch($batchid);

        $this->assertSame('1', (string)$DB->get_field('quiz', 'attempts', ['id' => $quiz->id], MUST_EXIST));
        $this->assertSame(
            'complete',
            $DB->get_field('tool_coursecompliance_act_batch', 'status', ['id' => $batchid], MUST_EXIST)
        );
    }
}
