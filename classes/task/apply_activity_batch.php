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
 * Classes for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\task;

/**
 * Apply a previewed activity operation batch.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class apply_activity_batch extends \core\task\adhoc_task {
    /**
     * Apply the queued activity-operation batch.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $batchid = (int)$data->batchid;
        try {
            (new \tool_coursecompliance\activity_manager())->apply_batch($batchid);
        } catch (\Throwable $e) {
            if ($batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid])) {
                $batch->status = 'failed';
                $batch->timefinished = time();
                $batch->errormessage = $e->getMessage();
                $DB->update_record('tool_coursecompliance_act_batch', $batch);
            }
            mtrace('Course Compliance activity batch #' . $batchid . ' failed: ' . $e->getMessage());
            return;
        }

        try {
            $this->queue_autofix_recheck($batchid);
        } catch (\Throwable $e) {
            // Recheck scheduling must never turn an already applied batch into a failed operation.
            mtrace('Course Compliance could not queue the automatic recheck: ' . $e->getMessage());
        }
    }

    /**
     * Queue a fresh audit after a successful auto-fix batch.
     *
     * @param int $batchid Applied batch id.
     */
    private function queue_autofix_recheck(int $batchid): void {
        global $DB;

        $batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid]);
        if (!$batch || $batch->action !== 'autofix_scan' || (int)$batch->applied <= 0) {
            return;
        }
        if (!in_array($batch->status, ['complete', 'partial'], true)) {
            return;
        }

        $filter = json_decode((string)$batch->filterjson, true);
        if (!is_array($filter) || !empty($filter['rescanid'])) {
            return;
        }
        $profileid = (int)($filter['profileid'] ?? 0);
        if ($profileid <= 0 || !$DB->record_exists('tool_coursecompliance_profile', ['id' => $profileid])) {
            return;
        }

        $courselimit = (int)($filter['courselimit'] ?? 0);
        $scanid = $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => (int)$batch->categoryid,
            'courselimit' => $courselimit,
            'userid' => (int)$batch->userid,
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
            'timecreated' => time(),
        ]);

        $task = new run_scan();
        $task->set_custom_data([
            'scanid' => $scanid,
            'courselimit' => $courselimit,
        ]);
        \core\task\manager::queue_adhoc_task($task);

        $filter['rescanid'] = $scanid;
        $batch->filterjson = json_encode($filter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $DB->update_record('tool_coursecompliance_act_batch', $batch);
        mtrace('Course Compliance queued automatic recheck scan #' . $scanid . ' after batch #' . $batchid . '.');
    }
}
