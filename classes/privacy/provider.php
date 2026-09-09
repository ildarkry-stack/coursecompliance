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
 * Privacy provider for Course compliance.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Course compliance data.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'tool_coursecompliance_scan',
            [
                'userid' => 'privacy:metadata:userid',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:scan'
        );
        $collection->add_database_table(
            'tool_coursecompliance_act_batch',
            [
                'userid' => 'privacy:metadata:activitybatch:userid',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:activitybatch'
        );
        $collection->add_database_table(
            'tool_coursecompliance_exception',
            [
                'userid' => 'privacy:metadata:exception:userid',
                'reason' => 'privacy:metadata:exception:reason',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:exception'
        );
        return $collection;
    }

    /**
     * Get contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if (
            $DB->record_exists('tool_coursecompliance_scan', ['userid' => $userid])
            || $DB->record_exists('tool_coursecompliance_act_batch', ['userid' => $userid])
            || $DB->record_exists('tool_coursecompliance_exception', ['userid' => $userid])
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Export personal data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (!$contexts) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records(
            'tool_coursecompliance_scan',
            ['userid' => $userid],
            'timecreated ASC',
            'id, profileid, categoryid, status, courses, assessments, critical, warnings, notices, ' .
                'startedat, finishedat, timecreated'
        );
        $audits = [];
        foreach ($records as $record) {
            $audits[] = (array)$record;
        }

        $batches = $DB->get_records(
            'tool_coursecompliance_act_batch',
            ['userid' => $userid],
            'timecreated ASC',
            'id, categoryid, action, status, summary, totalchanges, applied, skipped, failed, ' .
                'timecreated, timestarted, timefinished'
        );
        $operations = [];
        foreach ($batches as $batch) {
            $operations[] = (array)$batch;
        }

        $exceptions = $DB->get_records(
            'tool_coursecompliance_exception',
            ['userid' => $userid],
            'timecreated ASC',
            'id, profileid, courseid, rulecode, component, objectid, reason, expiresat, enabled, timecreated'
        );
        $exceptiondata = [];
        foreach ($exceptions as $exception) {
            $exceptiondata[] = (array)$exception;
        }

        if (!$audits && !$operations && !$exceptiondata) {
            return;
        }

        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'tool_coursecompliance')],
            (object)[
                'audits' => $audits,
                'activityoperations' => $operations,
                'exceptions' => $exceptiondata,
            ]
        );
    }

    /**
     * Delete all personal data in a context.
     *
     * @param \context $context Context to clear.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        self::delete_all_scans();
    }

    /**
     * Delete personal data for one user.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->get_contexts()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $scanids = $DB->get_fieldset_select(
            'tool_coursecompliance_scan',
            'id',
            'userid = :userid',
            ['userid' => $userid]
        );
        if ($scanids) {
            [$insql, $params] = $DB->get_in_or_equal($scanids, SQL_PARAMS_NAMED, 'scan');
            $DB->delete_records_select('tool_coursecompliance_result', "scanid $insql", $params);
            $DB->delete_records_select('tool_coursecompliance_assessment', "scanid $insql", $params);
            $DB->delete_records_select('tool_coursecompliance_scan', "id $insql", $params);
        }

        $DB->delete_records('tool_coursecompliance_exception', ['userid' => $userid]);
        $batchids = $DB->get_fieldset_select(
            'tool_coursecompliance_act_batch',
            'id',
            'userid = :userid',
            ['userid' => $userid]
        );
        if ($batchids) {
            [$insql, $params] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED, 'batch');
            $DB->delete_records_select('tool_coursecompliance_act_change', "batchid $insql", $params);
            $DB->delete_records_select('tool_coursecompliance_session_issue', "batchid $insql", $params);
            $DB->delete_records_select('tool_coursecompliance_act_batch', "id $insql", $params);
        }
    }

    /**
     * Delete all stored scan and operation data.
     */
    private static function delete_all_scans(): void {
        global $DB;

        $DB->delete_records('tool_coursecompliance_result');
        $DB->delete_records('tool_coursecompliance_assessment');
        $DB->delete_records('tool_coursecompliance_scan');
        $DB->delete_records('tool_coursecompliance_exception');
        $DB->delete_records('tool_coursecompliance_act_change');
        $DB->delete_records('tool_coursecompliance_session_issue');
        $DB->delete_records('tool_coursecompliance_act_batch');
    }
}
