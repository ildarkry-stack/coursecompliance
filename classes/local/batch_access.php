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

namespace tool_coursecompliance\local;

/**
 * Central authorization helper for change batches.
 *
 * @package tool_coursecompliance
 * @copyright 2026 MIA Academy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_access {
    /**
     * Whether the current user may manage a batch.
     *
     * @param \stdClass $batch Batch record.
     * @return bool
     */
    public static function can_manage(\stdClass $batch): bool {
        global $DB;
        if ($batch->action === 'session_schedule') {
            $context = \context_coursecat::instance((int)$batch->categoryid);
            return has_capability('tool/coursecompliance:scheduleassessments', $context);
        }
        if ($batch->action === 'rollback' && !empty($batch->sourcebatchid)) {
            $source = $DB->get_record('tool_cc_act_batch', ['id' => (int)$batch->sourcebatchid]);
            if ($source && $source->action === 'session_schedule') {
                $context = \context_coursecat::instance((int)$source->categoryid);
                return has_capability('tool/coursecompliance:scheduleassessments', $context);
            }
        }
        return has_capability('tool/coursecompliance:manageactivities', \context_system::instance());
    }

    /**
     * Require permission to apply or roll back a batch.
     *
     * @param \stdClass $batch Batch record.
     */
    public static function require_manage(\stdClass $batch): void {
        global $DB;
        if ($batch->action === 'session_schedule') {
            require_capability(
                'tool/coursecompliance:scheduleassessments',
                \context_coursecat::instance((int)$batch->categoryid)
            );
            return;
        }
        if ($batch->action === 'rollback' && !empty($batch->sourcebatchid)) {
            $source = $DB->get_record('tool_cc_act_batch', ['id' => (int)$batch->sourcebatchid]);
            if ($source && $source->action === 'session_schedule') {
                require_capability(
                    'tool/coursecompliance:scheduleassessments',
                    \context_coursecat::instance((int)$source->categoryid)
                );
                return;
            }
        }
        require_capability('tool/coursecompliance:manageactivities', \context_system::instance());
    }

}
