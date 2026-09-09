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
    /**
     * Execute the scan task.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $scanid = (int)$data->scanid;
        try {
            (new \tool_coursecompliance\scanner())->run($scanid);
        } catch (\Throwable $e) {
            if ($scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid])) {
                $scan->status = 'failed';
                $scan->finishedat = time();
                $scan->errormessage = $e->getMessage();
                $DB->update_record('tool_coursecompliance_scan', $scan);
            }
            throw $e;
        }
    }
}
