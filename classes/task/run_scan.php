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
 * Ad hoc task that runs a compliance scan.
 */
final class run_scan extends \core\task\adhoc_task {
    /** Automatic retry delay for a failed worker. */
    private const RETRY_DELAY = 30;

    /**
     * Execute the scan task.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        \tool_coursecompliance\operation_lock::run('worker:' . (int)($data->scanid ?? 0), function (): void {
            $this->execute_locked();
        });
    }

    /** Execute with exclusive ownership of the logical operation. */
    private function execute_locked(): void {
        global $DB;

        $data = $this->get_custom_data();
        $scanid = (int)($data->scanid ?? 0);
        $courselimit = (int)($data->courselimit ?? 0);
        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid]);
        if ($scan && $scan->status === 'complete') {
            return;
        }
        if (!$scan) {
            mtrace('Course compliance scan record ' . $scanid . ' no longer exists; task skipped.');
            return;
        }

        $profileid = (int)$scan->profileid;
        if ($profileid <= 0 || !$DB->record_exists('tool_coursecompliance_profile', ['id' => $profileid])) {
            $message = get_string('scaninvalidprofile', 'tool_coursecompliance');
            $scan->status = 'failed';
            $scan->finishedat = time();
            $scan->errormessage = $message;
            $DB->update_record('tool_coursecompliance_scan', $scan);
            mtrace($message . ' Scan id: ' . $scanid);
            return;
        }

        $performance = \tool_coursecompliance\scan_performance::start();
        try {
            (new \tool_coursecompliance\scanner())->run($scanid, $courselimit, false);
            $transaction = $DB->start_delegated_transaction();
            try {
                \tool_coursecompliance\scan_performance::store(
                    $scanid,
                    \tool_coursecompliance\scan_performance::finish($performance)
                );
                $DB->set_field('tool_coursecompliance_scan', 'status', 'complete', ['id' => $scanid]);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } catch (\Throwable $e) {
            $metrics = \tool_coursecompliance\scan_performance::finish($performance);
            if ($scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid])) {
                $scan->durationms += (int)$metrics['durationms'];
                $scan->dbqueries += (int)$metrics['dbqueries'];
                $scan->peakmemory = max((int)$scan->peakmemory, (int)$metrics['peakmemory']);
                $scan->errormessage = $e->getMessage();

                if (
                    (int)($scan->parentid ?? 0) > 0
                    && (int)($scan->retrycount ?? 0) < \tool_coursecompliance\scan_scheduler::max_batch_retries()
                ) {
                    $DB->update_record('tool_coursecompliance_scan', $scan);
                    \tool_coursecompliance\scan_scheduler::retry_child(
                        $scanid,
                        (int)$scan->userid,
                        self::RETRY_DELAY
                    );
                    mtrace(
                        'Course Compliance batch #' . (int)($scan->batchno ?? 0)
                        . ' failed and was queued for retry: ' . $e->getMessage()
                    );
                    return;
                }

                $scan->status = 'failed';
                $scan->finishedat = time();
                $DB->update_record('tool_coursecompliance_scan', $scan);
                if ((int)($scan->parentid ?? 0) > 0) {
                    mtrace('Course Compliance batch #' . (int)($scan->batchno ?? 0) . ' failed: ' . $e->getMessage());
                    return;
                }
            }
            throw $e;
        }
    }
}
