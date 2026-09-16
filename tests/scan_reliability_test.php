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
 * Reliability tests for batched scans.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Batched scan recovery tests.
 *
 * @covers \tool_coursecompliance\scan_scheduler
 * @covers \tool_coursecompliance\task\finalize_scan
 */
final class scan_reliability_test extends \advanced_testcase {
    /**
     * Failed workers can be retried without discarding successful workers.
     */
    public function test_retry_failed_batches_preserves_successful_workers(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->create_parent_with_children((int)$USER->id, false, 0);
        $failedbefore = $DB->get_record(
            'tool_coursecompliance_scan',
            ['id' => $ids['failed']],
            '*',
            MUST_EXIST
        );
        $this->assertSame(0, (int)$failedbefore->retrycount);

        $count = scan_scheduler::retry_failed_batches($ids['parent'], (int)$USER->id);
        $this->assertSame(1, $count);

        $failed = $DB->get_record('tool_coursecompliance_scan', ['id' => $ids['failed']], '*', MUST_EXIST);
        $complete = $DB->get_record('tool_coursecompliance_scan', ['id' => $ids['complete']], '*', MUST_EXIST);
        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $ids['parent']], '*', MUST_EXIST);

        $this->assertSame('queued', $failed->status);
        $this->assertSame((int)$failedbefore->retrycount + 1, (int)$failed->retrycount);
        $this->assertSame('complete', $complete->status);
        $this->assertSame('running', $parent->status);
        $this->assertSame(0, (int)$parent->finishedat);
    }

    /**
     * A failed parent with completed children can retry only the aggregation step.
     */
    public function test_retry_failed_parent_can_requeue_finalizer_only(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->create_parent_with_children(0, true);
        $DB->set_field('tool_coursecompliance_scan', 'status', 'failed', ['id' => $ids['parent']]);
        $DB->set_field('tool_coursecompliance_scan', 'finishedat', time(), ['id' => $ids['parent']]);

        $this->assertSame(-1, scan_scheduler::retry_failed_batches($ids['parent'], 0));
        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $ids['parent']], '*', MUST_EXIST);
        $this->assertSame('running', $parent->status);
        $this->assertSame(0, (int)$parent->finishedat);
    }

    /**
     * The finalizer aggregates metrics and deletes internal worker rows.
     */
    public function test_finalizer_aggregates_worker_metrics(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->create_parent_with_children(0, true, 1);

        $task = new \tool_coursecompliance\task\finalize_scan();
        $task->set_custom_data(['scanid' => $ids['parent']]);
        $task->execute();

        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $ids['parent']], '*', MUST_EXIST);
        $this->assertSame('complete', $parent->status);
        $this->assertSame(300, (int)$parent->durationms);
        $this->assertSame(30, (int)$parent->dbqueries);
        $this->assertSame(4096, (int)$parent->peakmemory);
        $this->assertSame(1, (int)$parent->retrycount);
        $this->assertFalse($DB->record_exists('tool_coursecompliance_scan', ['parentid' => $ids['parent']]));
    }

    /**
     * Default automatic retry count is conservative and configurable.
     */
    public function test_retry_limit_defaults_to_one_and_is_clamped(): void {
        $this->resetAfterTest();
        unset_config('scanbatchretries', 'tool_coursecompliance');
        $this->assertSame(1, scan_scheduler::max_batch_retries());

        set_config('scanbatchretries', 3, 'tool_coursecompliance');
        $this->assertSame(3, scan_scheduler::max_batch_retries());

        set_config('scanbatchretries', 99, 'tool_coursecompliance');
        $this->assertSame(3, scan_scheduler::max_batch_retries());
    }

    /**
     * Create a parent scan with two workers.
     *
     * @param int $userid User id.
     * @param bool $bothcomplete Whether both children should be complete.
     * @param int $secondretries Initial retry count for the second worker.
     * @return array
     */
    private function create_parent_with_children(
        int $userid,
        bool $bothcomplete = false,
        int $secondretries = 0
    ): array {
        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $profileid = $DB->insert_record('tool_coursecompliance_profile', (object)[
            'name' => 'Reliability profile',
            'description' => '',
            'enabled' => 1,
            'configjson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $parentid = $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => $category->id,
            'courselimit' => 0,
            'scopejson' => null,
            'scantype' => 'audit',
            'parentid' => 0,
            'batchno' => 0,
            'batchtotal' => 2,
            'durationms' => 0,
            'dbqueries' => 0,
            'peakmemory' => 0,
            'retrycount' => 0,
            'userid' => $userid,
            'status' => $bothcomplete ? 'running' : 'failed',
            'finishedat' => $bothcomplete ? 0 : time(),
            'timecreated' => time(),
        ]);

        $completeid = $this->insert_child($parentid, $profileid, $category->id, $userid, 1, 'complete', 100, 10, 2048, 0);
        $secondstatus = $bothcomplete ? 'complete' : 'failed';
        $failedid = $this->insert_child(
            $parentid,
            $profileid,
            $category->id,
            $userid,
            2,
            $secondstatus,
            200,
            20,
            4096,
            $secondretries
        );
        return ['parent' => $parentid, 'complete' => $completeid, 'failed' => $failedid];
    }

    /**
     * Insert a worker scan.
     *
     * @param int $parentid Parent id.
     * @param int $profileid Profile id.
     * @param int $categoryid Category id.
     * @param int $userid User id.
     * @param int $batchno Batch number.
     * @param string $status Status.
     * @param int $duration Duration in milliseconds.
     * @param int $queries Query count.
     * @param int $memory Peak memory.
     * @param int $retries Retry count.
     * @return int
     */
    private function insert_child(
        int $parentid,
        int $profileid,
        int $categoryid,
        int $userid,
        int $batchno,
        string $status,
        int $duration,
        int $queries,
        int $memory,
        int $retries
    ): int {
        global $DB;

        return $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => $categoryid,
            'courselimit' => 0,
            'scopejson' => json_encode(['courseids' => [$batchno]]),
            'scantype' => 'batch',
            'parentid' => $parentid,
            'batchno' => $batchno,
            'batchtotal' => 2,
            'durationms' => $duration,
            'dbqueries' => $queries,
            'peakmemory' => $memory,
            'retrycount' => $retries,
            'userid' => $userid,
            'status' => $status,
            'courses' => 1,
            'assessments' => 1,
            'critical' => 0,
            'warnings' => 0,
            'notices' => 0,
            'ignored' => 0,
            'startedat' => time() - 1,
            'finishedat' => time(),
            'errormessage' => $status === 'failed' ? 'Synthetic worker failure' : '',
            'timecreated' => time(),
        ]);
    }
}
