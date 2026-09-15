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
 * Tests for SQL-backed current issue queries.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Action Center repository tests.
 *
 * @covers \tool_coursecompliance\action_center_repository
 */
final class action_center_repository_test extends \advanced_testcase {
    /**
     * A newer clean targeted audit suppresses an older problem.
     */
    public function test_newer_clean_scan_hides_old_problem(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $profileid = $DB->insert_record('tool_coursecompliance_profile', (object)[
            'name' => 'Current state test',
            'description' => '',
            'enabled' => 1,
            'configjson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $oldscanid = $this->insert_scan($profileid, $category->id, (int)$USER->id, 100);
        $this->insert_snapshot($oldscanid, $course->id, 'critical');
        $DB->insert_record('tool_coursecompliance_result', (object)[
            'scanid' => $oldscanid,
            'courseid' => $course->id,
            'component' => 'quiz',
            'assessmenttype' => 'exam',
            'rulecode' => 'QUIZ_TEST_PROBLEM',
            'severity' => 'critical',
            'objectid' => 1,
            'objectname' => 'Exam',
            'expected' => 'OK',
            'actual' => 'Bad',
            'message' => 'Old problem',
            'timecreated' => 100,
        ]);
        $this->assertSame(1, action_center_repository::count());

        $newscanid = $this->insert_scan($profileid, $category->id, (int)$USER->id, 200);
        $this->insert_snapshot($newscanid, $course->id, 'ready');
        $this->assertSame(0, action_center_repository::count());
        $this->assertSame([], action_center_repository::page('', '', 0, 100));
    }

    /**
     * Insert completed scan.
     *
     * @param int $profileid Profile id.
     * @param int $categoryid Category id.
     * @param int $userid User id.
     * @param int $time Time.
     * @return int
     */
    private function insert_scan(int $profileid, int $categoryid, int $userid, int $time): int {
        global $DB;

        return $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => $categoryid,
            'courselimit' => 0,
            'scopejson' => null,
            'scantype' => 'audit',
            'parentid' => 0,
            'batchno' => 0,
            'batchtotal' => 0,
            'userid' => $userid,
            'status' => 'complete',
            'finishedat' => $time,
            'timecreated' => $time,
        ]);
    }

    /**
     * Insert course snapshot.
     *
     * @param int $scanid Scan id.
     * @param int $courseid Course id.
     * @param string $status Readiness status.
     */
    private function insert_snapshot(int $scanid, int $courseid, string $status): void {
        global $DB;

        $DB->insert_record('tool_coursecompliance_course', (object)[
            'scanid' => $scanid,
            'courseid' => $courseid,
            'critical' => $status === 'critical' ? 1 : 0,
            'warnings' => 0,
            'notices' => 0,
            'ignored' => 0,
            'score' => $status === 'critical' ? 75 : 100,
            'status' => $status,
            'timecreated' => time(),
        ]);
    }
}
