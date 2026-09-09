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
        }
    }
}
