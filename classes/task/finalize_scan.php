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
 * Parent scan finalizer task.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\task;

/**
 * Aggregates worker scans after all batches have completed.
 */
final class finalize_scan extends \core\task\adhoc_task {
    /** Maximum finalizer polling delay in seconds. */
    private const RETRY_DELAY = 20;

    /**
     * Execute task.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $scanid = (int)($data->scanid ?? 0);
        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid]);
        if (!$parent || in_array((string)$parent->status, ['complete', 'failed'], true)) {
            return;
        }

        $children = $DB->get_records(
            'tool_coursecompliance_scan',
            ['parentid' => $scanid],
            'batchno ASC, id ASC'
        );
        $expected = max(1, (int)$parent->batchtotal);
        if (count($children) < $expected || self::has_pending_children($children)) {
            \tool_coursecompliance\scan_scheduler::queue_finalizer(
                $scanid,
                (int)$parent->userid,
                self::RETRY_DELAY
            );
            return;
        }

        $failed = array_filter(
            $children,
            static fn (\stdClass $child): bool => (string)$child->status === 'failed'
        );
        if ($failed) {
            $messages = [];
            foreach ($failed as $child) {
                $messages[] = '#' . (int)$child->batchno . ': ' . trim((string)$child->errormessage);
            }
            $parent->status = 'failed';
            $parent->finishedat = time();
            $parent->errormessage = implode("\n", array_filter($messages));
            $DB->update_record('tool_coursecompliance_scan', $parent);
            \tool_coursecompliance\scan_notifier::failed($parent);
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            self::clear_parent_data($scanid);
            foreach ($children as $child) {
                self::copy_rows('tool_coursecompliance_result', (int)$child->id, $scanid);
                self::copy_rows('tool_coursecompliance_assessment', (int)$child->id, $scanid);
                self::copy_rows('tool_coursecompliance_course', (int)$child->id, $scanid);
                self::copy_rows('tool_coursecompliance_qissue', (int)$child->id, $scanid);
            }

            $parent->status = 'complete';
            $parent->courses = array_sum(array_map(
                static fn (\stdClass $child): int => (int)$child->courses,
                $children
            ));
            $parent->assessments = array_sum(array_map(
                static fn (\stdClass $child): int => (int)$child->assessments,
                $children
            ));
            $parent->critical = array_sum(array_map(
                static fn (\stdClass $child): int => (int)$child->critical,
                $children
            ));
            $parent->warnings = array_sum(array_map(
                static fn (\stdClass $child): int => (int)$child->warnings,
                $children
            ));
            $parent->notices = array_sum(array_map(
                static fn (\stdClass $child): int => (int)$child->notices,
                $children
            ));
            $parent->ignored = array_sum(array_map(
                static fn (\stdClass $child): int => (int)$child->ignored,
                $children
            ));
            $parent->finishedat = time();
            $parent->errormessage = '';
            $DB->update_record('tool_coursecompliance_scan', $parent);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        self::delete_children($children);
        \tool_coursecompliance\scan_notifier::completed($parent);
    }

    /**
     * Whether some worker scans are still queued/running.
     *
     * @param \stdClass[] $children Worker scans.
     * @return bool
     */
    private static function has_pending_children(array $children): bool {
        foreach ($children as $child) {
            if (in_array((string)$child->status, ['queued', 'running'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete any old parent result rows before aggregation.
     *
     * @param int $scanid Parent scan id.
     */
    private static function clear_parent_data(int $scanid): void {
        global $DB;

        foreach (self::detail_tables() as $table) {
            $DB->delete_records($table, ['scanid' => $scanid]);
        }
    }

    /**
     * Copy child rows into parent scan.
     *
     * @param string $table Table name.
     * @param int $childid Child scan id.
     * @param int $parentid Parent scan id.
     */
    private static function copy_rows(string $table, int $childid, int $parentid): void {
        global $DB;

        $recordset = $DB->get_recordset($table, ['scanid' => $childid]);
        foreach ($recordset as $record) {
            unset($record->id);
            $record->scanid = $parentid;
            $DB->insert_record($table, $record);
        }
        $recordset->close();
    }

    /**
     * Delete successful worker scan storage after parent aggregation.
     *
     * @param \stdClass[] $children Child scans.
     */
    private static function delete_children(array $children): void {
        global $DB;

        foreach ($children as $child) {
            foreach (self::detail_tables() as $table) {
                $DB->delete_records($table, ['scanid' => (int)$child->id]);
            }
            $DB->delete_records('tool_coursecompliance_scan', ['id' => (int)$child->id]);
        }
    }

    /**
     * Scan detail tables.
     *
     * @return string[]
     */
    private static function detail_tables(): array {
        return [
            'tool_coursecompliance_result',
            'tool_coursecompliance_assessment',
            'tool_coursecompliance_course',
            'tool_coursecompliance_qissue',
        ];
    }
}
