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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Regression coverage for the 1.1 reliability and reporting changes.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Exercises persisted state rather than only helper return values.
 *
 * @covers \tool_coursecompliance\scan_snapshot
 * @covers \tool_coursecompliance\scan_scheduler
 * @covers \tool_coursecompliance\course_indicator
 * @covers \tool_coursecompliance\report_repository
 * @covers \tool_coursecompliance\question_issue_repository
 * @covers \tool_coursecompliance\current_scan
 * @covers \tool_coursecompliance\activity_manager
 * @covers \tool_coursecompliance\task\cleanup_scans
 * @covers \tool_coursecompliance\task\finalize_scan
 */
final class release_regression_test extends \advanced_testcase {
    /** A profile edit cannot change an already scheduled audit. */
    public function test_snapshot_and_idempotent_scheduling(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $profileid, $scanid] = $this->fixture();
        $before = scan_snapshot::config($DB->get_record('tool_coursecompliance_scan', ['id' => $scanid]));
        $DB->set_field('tool_coursecompliance_scan', 'status', 'queued', ['id' => $scanid]);
        $this->assertSame(1, scan_scheduler::queue($scanid, [$course->id], (int)$USER->id));
        $this->assertSame(1, scan_scheduler::queue($scanid, [$course->id], (int)$USER->id));
        $this->assertSame(1, $DB->count_records('tool_coursecompliance_scan', ['parentid' => $scanid]));
        $changed = $before;
        $changed['examattempts'] = 17;
        $DB->set_field('tool_coursecompliance_profile', 'configjson', profile_config::encode($changed), ['id' => $profileid]);
        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid]);
        $child = $DB->get_record('tool_coursecompliance_scan', ['parentid' => $scanid]);
        $this->assertSame($before, scan_snapshot::config($parent));
        $this->assertSame($before, scan_snapshot::config($child));
        $this->assertSame(hash('sha256', $parent->configsnapshot), $child->confighash);
    }

    /** Private worker snapshots never override a published course indicator. */
    public function test_indicator_ignores_finished_child_of_running_parent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $profileid, $oldid] = $this->fixture();
        $this->violation($oldid, $course->id);
        $parentid = $this->scan($profileid, $course->category, 'running');
        $childid = $this->scan($profileid, $course->category, 'complete', $parentid);
        $DB->insert_record('tool_coursecompliance_course', (object)['scanid' => $childid, 'courseid' => $course->id]);
        $cache = new \ReflectionProperty(course_indicator::class, 'cache');
        $cache->setValue(null, []);
        $indicator = course_indicator::get_for_course($course->id, $course->category);
        $this->assertSame($oldid, $indicator->scanid);
        $this->assertSame(1, $indicator->critical);
    }

    /** Regular and semantic pages retain full totals and stable ordering. */
    public function test_report_pages_and_semantic_filters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, , $scanid] = $this->fixture();
        for ($i = 0; $i < 105; $i++) {
            $this->violation($scanid, $course->id, 'QUIZ_EXAM_REQUIRED');
        }
        $params = ['scanid' => $scanid];
        [$first, $total] = report_repository::page('r.scanid = :scanid', $params, '', [], 0, 100);
        [$second, $secondtotal] = report_repository::page('r.scanid = :scanid', $params, '', [], 1, 100);
        $this->assertCount(100, $first);
        $this->assertCount(5, $second);
        $this->assertSame(105, $total);
        $this->assertSame($total, $secondtotal);
        $this->assertSame([], array_intersect_key($first, $second));
        [$missing, $missingtotal] = report_repository::page('r.scanid = :scanid', $params, 'missing', [], 1, 100);
        $this->assertCount(5, $missing);
        $this->assertSame(105, $missingtotal);
        [$mismatch, $mismatchtotal] = report_repository::page('r.scanid = :scanid', $params, 'mismatch', [], 0, 100);
        $this->assertSame([], $mismatch);
        $this->assertSame(0, $mismatchtotal);
    }

    /** A question filter is applied before pagination and newer clean scans suppress old details. */
    public function test_question_filters_and_clean_overlay(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $profileid, $scanid] = $this->fixture();
        $this->violation($scanid, $course->id, 'QUIZ_QUESTION_CORRECT_ANSWER_REQUIRED');
        for ($i = 0; $i < 105; $i++) {
            $DB->insert_record('tool_coursecompliance_qissue', (object)[
                'scanid' => $scanid, 'courseid' => $course->id, 'assessmenttype' => 'exam',
                'issuecode' => 'no_correct_answer', 'severity' => 'critical',
                'questionname' => $i === 0 ? 'Needle' : 'Other', 'qtype' => 'multichoice',
            ]);
        }
        $this->assertSame(105, question_issue_repository::current_count());
        $this->assertCount(5, question_issue_repository::current_page([], 1, 100));
        $rows = question_issue_repository::current_page(['search' => 'Needle'], 0, 100);
        $this->assertCount(1, $rows);
        $this->assertSame('Needle', $rows[0]->questionname);
        $newid = $this->scan($profileid, $course->category);
        $DB->insert_record('tool_coursecompliance_course', (object)['scanid' => $newid, 'courseid' => $course->id]);
        $this->assertSame(0, question_issue_repository::current_count());
    }

    /** Continuations keep applied rows and counters instead of relabelling them as conflicts. */
    public function test_activity_continuation_preserves_applied_changes(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->fixture();
        $batchid = $DB->insert_record('tool_coursecompliance_act_batch', (object)[
            'userid' => $USER->id, 'categoryid' => $course->category, 'action' => 'rename',
            'status' => 'queued', 'summary' => 'Resume test', 'totalchanges' => 2,
        ]);
        foreach (['One', 'Two'] as $name) {
            $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => $name]);
            $DB->insert_record('tool_coursecompliance_act_change', (object)[
                'batchid' => $batchid, 'cmid' => $quiz->cmid, 'courseid' => $course->id,
                'modname' => 'quiz', 'instanceid' => $quiz->id,
                'fieldname' => 'name', 'oldvalue' => $name, 'newvalue' => $name . ' updated', 'status' => 'pending',
            ]);
        }
        $manager = new activity_manager();
        $this->assertFalse($manager->apply_batch($batchid, 1));
        $this->assertSame(1, $DB->count_records(
            'tool_coursecompliance_act_change',
            ['batchid' => $batchid, 'status' => 'applied']
        ));
        $this->assertTrue($manager->apply_batch($batchid, 1));
        $this->assertTrue($manager->apply_batch($batchid, 1));
        $batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid]);
        $this->assertSame(2, (int)$batch->applied);
        $this->assertSame(0, (int)$batch->skipped);
        $this->assertSame('complete', $batch->status);
    }

    /** Retention does not destroy active or failed recovery state. */
    public function test_cleanup_preserves_recoverable_scans(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('retentiondays', 1, 'tool_coursecompliance');
        [$course, $profileid, $completeid] = $this->fixture();
        $runningid = $this->scan($profileid, $course->category, 'running');
        $failedid = $this->scan($profileid, $course->category, 'failed');
        foreach ([$completeid, $runningid, $failedid] as $id) {
            $DB->update_record('tool_coursecompliance_scan', (object)[
                'id' => $id, 'timecreated' => time() - 3 * DAYSECS, 'finishedat' => time() - 2 * DAYSECS,
            ]);
        }
        (new \tool_coursecompliance\task\cleanup_scans())->execute();
        $this->assertFalse($DB->record_exists('tool_coursecompliance_scan', ['id' => $completeid]));
        $this->assertTrue($DB->record_exists('tool_coursecompliance_scan', ['id' => $runningid]));
        $this->assertTrue($DB->record_exists('tool_coursecompliance_scan', ['id' => $failedid]));
    }

    /** Grouped publication preserves rows and is idempotent. */
    public function test_finalizer_publishes_detail_rows_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $profileid, $parentid] = $this->fixture();
        $DB->update_record('tool_coursecompliance_scan', (object)['id' => $parentid, 'status' => 'running', 'batchtotal' => 1]);
        $childid = $this->scan($profileid, $course->category, 'complete', $parentid);
        $this->violation($childid, $course->id);
        $DB->insert_record('tool_coursecompliance_course', (object)['scanid' => $childid, 'courseid' => $course->id]);
        $task = new \tool_coursecompliance\task\finalize_scan();
        $task->set_custom_data(['scanid' => $parentid]);
        $task->execute();
        $task->execute();
        $this->assertSame(1, $DB->count_records('tool_coursecompliance_result', ['scanid' => $parentid]));
        $this->assertSame(1, $DB->count_records('tool_coursecompliance_course', ['scanid' => $parentid]));
        $this->assertFalse($DB->record_exists('tool_coursecompliance_scan', ['id' => $childid]));
        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $parentid]);
        $this->assertSame('complete', $parent->status);
        $this->assertGreaterThan(0, (int)$parent->finaldbqueries);
    }

    /**
     * Create an audited course and enabled profile.
     *
     * @return array Course, profile id and published scan id.
     */
    private function fixture(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $profileid = $DB->insert_record('tool_coursecompliance_profile', (object)[
            'name' => 'Regression profile', 'enabled' => 1,
            'configjson' => profile_config::encode(profile_config::defaults()),
        ]);
        $scanid = $this->scan($profileid, $course->category);
        $DB->insert_record('tool_coursecompliance_course', (object)['scanid' => $scanid, 'courseid' => $course->id]);
        return [$course, $profileid, $scanid];
    }

    /**
     * Create an audit row.
     *
     * @param int $profileid Profile id.
     * @param int $categoryid Category id.
     * @param string $status Status.
     * @param int $parentid Parent id, zero for public scans.
     * @return int Scan id.
     */
    private function scan(int $profileid, int $categoryid, string $status = 'complete', int $parentid = 0): int {
        global $DB;
        return $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid, 'categoryid' => $categoryid, 'userid' => 0,
            'status' => $status, 'parentid' => $parentid, 'scantype' => $parentid ? 'batch' : 'audit',
            'timecreated' => time(), 'finishedat' => $status === 'complete' ? time() : 0,
        ]);
    }

    /**
     * Create one report violation.
     *
     * @param int $scanid Audit id.
     * @param int $courseid Course id.
     * @param string $rulecode Rule code.
     */
    private function violation(int $scanid, int $courseid, string $rulecode = 'QUIZ_TEST_PROBLEM'): void {
        global $DB;
        $DB->insert_record('tool_coursecompliance_result', (object)[
            'scanid' => $scanid, 'courseid' => $courseid, 'component' => 'quiz',
            'assessmenttype' => 'exam', 'rulecode' => $rulecode, 'severity' => 'critical',
            'objectid' => 0, 'objectname' => 'Exam', 'expected' => 'Present', 'actual' => 'missing', 'message' => 'Test',
        ]);
    }
}
