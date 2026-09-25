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
 * Scan scheduling helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Splits large scans into independent Moodle ad-hoc task batches.
 */
final class scan_scheduler {
    /** Default number of courses processed by one worker task. */
    private const DEFAULT_BATCH_SIZE = 10;

    /** Maximum supported worker batch size. */
    private const MAX_BATCH_SIZE = 50;

    /**
     * Queue a parent scan as multiple worker scans.
     *
     * @param int $scanid Parent scan id.
     * @param int[] $courseids Courses to check.
     * @param int $userid User that initiated the scan.
     * @return int Number of queued batches.
     */
    public static function queue(int $scanid, array $courseids, int $userid = 0): int {
        return operation_lock::run('scan:' . $scanid, static function () use ($scanid, $courseids, $userid): int {
            return self::queue_locked($scanid, $courseids, $userid);
        });
    }

    /**
     * Publish a complete task tree atomically under the parent lock.
     *
     * @param int $scanid Parent id.
     * @param array $courseids Selected courses.
     * @param int $userid Initiator.
     * @return int Batch count.
     */
    private static function queue_locked(int $scanid, array $courseids, int $userid): int {
        global $DB;

        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!$courseids) {
            throw new \moodle_exception('scanrequirescourses', 'tool_coursecompliance');
        }

        if ($DB->record_exists('tool_coursecompliance_scan', ['parentid' => $scanid])) {
            return (int)$parent->batchtotal;
        }
        if ($parent->status !== 'queued' || (int)$parent->parentid > 0) {
            throw new \coding_exception('Only a queued parent scan can be scheduled.');
        }
        $transaction = $DB->start_delegated_transaction();
        scan_snapshot::capture($parent);
        $batchsize = self::batch_size();
        $chunks = array_chunk($courseids, $batchsize);
        $batchtotal = count($chunks);
        $now = time();

        $parent->status = 'running';
        $parent->startedat = $parent->startedat ?: $now;
        $parent->courses = count($courseids);
        $parent->batchtotal = $batchtotal;
        $DB->update_record('tool_coursecompliance_scan', $parent);

        foreach ($chunks as $index => $chunk) {
            $batchno = $index + 1;
            $child = (object)[
                'profileid' => (int)$parent->profileid,
                'categoryid' => (int)$parent->categoryid,
                'courselimit' => 0,
                'scopejson' => json_encode(
                    [
                        'courseids' => $chunk,
                        'parentid' => $scanid,
                        'batchno' => $batchno,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'scantype' => 'batch',
                'configsnapshot' => $parent->configsnapshot,
                'confighash' => $parent->confighash,
                'parentid' => $scanid,
                'batchno' => $batchno,
                'batchtotal' => $batchtotal,
                'durationms' => 0,
                'dbqueries' => 0,
                'peakmemory' => 0,
                'retrycount' => 0,
                'userid' => $userid ?: (int)$parent->userid,
                'status' => 'queued',
                'courses' => 0,
                'assessments' => 0,
                'critical' => 0,
                'warnings' => 0,
                'notices' => 0,
                'ignored' => 0,
                'startedat' => 0,
                'finishedat' => 0,
                'errormessage' => '',
                'timecreated' => $now,
            ];
            $childid = $DB->insert_record('tool_coursecompliance_scan', $child);

            $task = new \tool_coursecompliance\task\run_scan();
            $task->set_custom_data([
                'scanid' => $childid,
                'courselimit' => 0,
            ]);
            if ($userid > 0) {
                $task->set_userid($userid);
            }
            \core\task\manager::queue_adhoc_task($task);
        }

        self::queue_finalizer($scanid, $userid, 15);
        $transaction->allow_commit();
        return $batchtotal;
    }

    /**
     * Queue another finalizer pass.
     *
     * @param int $scanid Parent scan id.
     * @param int $userid Initiating user id.
     * @param int $delay Delay in seconds.
     */
    public static function queue_finalizer(int $scanid, int $userid = 0, int $delay = 0): void {
        $task = new \tool_coursecompliance\task\finalize_scan();
        $task->set_custom_data(['scanid' => $scanid]);
        if ($userid > 0) {
            $task->set_userid($userid);
        }
        if ($delay > 0) {
            $task->set_next_run_time(time() + $delay);
        }
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Requeue every failed worker of a parent scan.
     *
     * Successful workers are preserved and will be reused by the finalizer.
     *
     * @param int $scanid Parent scan id.
     * @param int $userid User requesting the retry.
     * @return int Number of workers requeued, or -1 when only finalization was requeued.
     */
    public static function retry_failed_batches(int $scanid, int $userid = 0): int {
        return operation_lock::run('scan:' . $scanid, static function () use ($scanid, $userid): int {
            return self::retry_failed_locked($scanid, $userid);
        });
    }

    /**
     * Retry under the same lock used to publish the parent result.
     *
     * @param int $scanid Parent id.
     * @param int $userid Initiator.
     * @return int Number of requeued children.
     */
    private static function retry_failed_locked(int $scanid, int $userid): int {
        global $DB;

        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        if ($parent->status !== 'failed') {
            return 0;
        }
        $failed = $DB->get_records('tool_coursecompliance_scan', [
            'parentid' => $scanid,
            'status' => 'failed',
        ], 'batchno ASC, id ASC');
        if (!$failed) {
            $children = $DB->get_records('tool_coursecompliance_scan', ['parentid' => $scanid]);
            if ($children) {
                $parent->status = 'running';
                $parent->finishedat = 0;
                $parent->errormessage = '';
                $DB->update_record('tool_coursecompliance_scan', $parent);
                self::queue_finalizer($scanid, $userid ?: (int)$parent->userid, 5);
                return -1;
            }
            return 0;
        }

        $parent->status = 'running';
        $parent->finishedat = 0;
        $parent->errormessage = '';
        $DB->update_record('tool_coursecompliance_scan', $parent);

        foreach ($failed as $child) {
            self::retry_child((int)$child->id, $userid ?: (int)$parent->userid);
        }
        self::queue_finalizer($scanid, $userid ?: (int)$parent->userid, 15);
        return count($failed);
    }

    /**
     * Requeue one worker scan.
     *
     * @param int $scanid Worker scan id.
     * @param int $userid User associated with the task.
     * @param int $delay Optional delay in seconds.
     */
    public static function retry_child(int $scanid, int $userid = 0, int $delay = 0): void {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        if ((int)$scan->parentid <= 0) {
            throw new \coding_exception('Only worker scans can be retried with retry_child().');
        }
        $scan->status = 'queued';
        $scan->startedat = 0;
        $scan->finishedat = 0;
        $scan->retrycount = (int)$scan->retrycount + 1;
        $DB->update_record('tool_coursecompliance_scan', $scan);

        $task = new \tool_coursecompliance\task\run_scan();
        $task->set_custom_data([
            'scanid' => $scanid,
            'courselimit' => 0,
        ]);
        if ($userid > 0) {
            $task->set_userid($userid);
        }
        if ($delay > 0) {
            $task->set_next_run_time(time() + $delay);
        }
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Maximum automatic retries for a failed worker.
     *
     * @return int
     */
    public static function max_batch_retries(): int {
        $configured = get_config('tool_coursecompliance', 'scanbatchretries');
        if ($configured === false) {
            return 1;
        }
        return max(0, min(3, (int)$configured));
    }

    /**
     * Configured worker batch size.
     *
     * @return int
     */
    public static function batch_size(): int {
        $configured = (int)get_config('tool_coursecompliance', 'scanbatchsize');
        if ($configured <= 0) {
            $configured = self::DEFAULT_BATCH_SIZE;
        }
        return max(1, min(self::MAX_BATCH_SIZE, $configured));
    }
}
