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
 * Scheduled task that removes expired audit and operation history.
 */
final class cleanup_scans extends \core\task\scheduled_task {
    /**
     * Get the localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanup', 'tool_coursecompliance');
    }
    /**
     * Delete expired scan and batch records.
     */
    public function execute(): void {
        global $DB;
        $days = max(1, (int)get_config('tool_coursecompliance', 'retentiondays'));
        $cutoff = time() - ($days * DAYSECS);
        $scans = $DB->get_records_select(
            'tool_coursecompliance_scan',
            'parentid = 0 AND status = :status AND finishedat > 0 AND finishedat < :cutoff',
            ['status' => 'complete', 'cutoff' => $cutoff],
            'id ASC',
            'id',
            0,
            100
        );
        foreach ($scans as $scan) {
            \tool_coursecompliance\operation_lock::run('scan:' . $scan->id, static function () use ($DB, $scan): void {
                $current = $DB->get_record('tool_coursecompliance_scan', ['id' => $scan->id]);
                if (!$current || $current->status !== 'complete') {
                    return;
                }
                // Legacy successful children must not be discarded while another task still owns them.
                if ($DB->record_exists('tool_coursecompliance_scan', ['parentid' => $scan->id])) {
                    return;
                }
                $transaction = $DB->start_delegated_transaction();
                foreach (['result', 'assessment', 'course', 'qissue'] as $suffix) {
                    $DB->delete_records('tool_coursecompliance_' . $suffix, ['scanid' => $scan->id]);
                }
                $DB->delete_records('tool_coursecompliance_scan', ['id' => $scan->id]);
                $transaction->allow_commit();
            });
        }
        $batches = $DB->get_records_select(
            'tool_coursecompliance_act_batch',
            "status IN ('complete', 'partial') AND timefinished > 0 AND timefinished < :cutoff",
            ['cutoff' => $cutoff],
            'id ASC',
            'id',
            0,
            100
        );
        foreach ($batches as $batch) {
            \tool_coursecompliance\operation_lock::run('activity:' . $batch->id, static function () use ($DB, $batch): void {
                $current = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batch->id]);
                if (!$current || !in_array($current->status, ['complete', 'partial'], true)) {
                    return;
                }
                $active = $DB->record_exists_select(
                    'tool_coursecompliance_act_batch',
                    "sourcebatchid = :sourceid AND status IN ('preview', 'queued', 'running', 'failed')",
                    ['sourceid' => $batch->id]
                );
                if ($active) {
                    return;
                }
                $transaction = $DB->start_delegated_transaction();
                $DB->delete_records('tool_coursecompliance_act_change', ['batchid' => $batch->id]);
                $DB->delete_records('tool_coursecompliance_session_issue', ['batchid' => $batch->id]);
                $DB->delete_records('tool_coursecompliance_act_batch', ['id' => $batch->id]);
                $transaction->allow_commit();
            });
        }
    }
}
