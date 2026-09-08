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

defined('MOODLE_INTERNAL') || die();

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
        $oldscans = $DB->get_fieldset_select('tool_cc_scan', 'id', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        if ($oldscans) {
            [$insql, $params] = $DB->get_in_or_equal($oldscans, SQL_PARAMS_NAMED, 'scan');
            $DB->delete_records_select('tool_cc_result', "scanid $insql", $params);
            $DB->delete_records_select('tool_cc_assessment', "scanid $insql", $params);
            $DB->delete_records_select('tool_cc_scan', "id $insql", $params);
        }
        $oldbatches = $DB->get_fieldset_select('tool_cc_act_batch', 'id', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        if ($oldbatches) {
            [$insql, $params] = $DB->get_in_or_equal($oldbatches, SQL_PARAMS_NAMED, 'batch');
            $DB->delete_records_select('tool_cc_act_change', "batchid $insql", $params);
            $DB->delete_records_select('tool_cc_session_issue', "batchid $insql", $params);
            $DB->delete_records_select('tool_cc_act_batch', "id $insql", $params);
        }
    }
}
