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
        global $DB;

        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!$courseids) {
            throw new \moodle_exception('scanrequirescourses', 'tool_coursecompliance');
        }

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
                'parentid' => $scanid,
                'batchno' => $batchno,
                'batchtotal' => $batchtotal,
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
