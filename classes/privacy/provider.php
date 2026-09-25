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
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Course compliance data.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
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
                'configsnapshot' => 'privacy:metadata:configsnapshot',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:scan'
        );
        $collection->add_database_table(
            'tool_coursecompliance_act_batch',
            [
                'userid' => 'privacy:metadata:activitybatch:userid',
                'filterjson' => 'privacy:metadata:configsnapshot',
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
        $collection->add_subsystem_link(
            'core_message',
            [],
            'privacy:metadata:coremessage'
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
     * Get users whose personal data is stored in the supplied context.
     *
     * CourseCompliance stores administrator-owned audit metadata in the system context.
     *
     * @param userlist $userlist User list for a context and component.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT userid FROM {tool_coursecompliance_scan} WHERE userid > 0
                 UNION
                SELECT userid FROM {tool_coursecompliance_act_batch} WHERE userid > 0
                 UNION
                SELECT userid FROM {tool_coursecompliance_exception} WHERE userid > 0";
        $userlist->add_from_sql('userid', $sql, []);
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

        $systemcontextid = \context_system::instance()->id;
        $approvedsystemcontext = false;
        foreach ($contexts as $context) {
            if ((int)$context->id === (int)$systemcontextid) {
                $approvedsystemcontext = true;
                break;
            }
        }
        if (!$approvedsystemcontext) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records(
            'tool_coursecompliance_scan',
            ['userid' => $userid],
            'timecreated ASC',
            'id, profileid, categoryid, courselimit, scantype, status, courses, assessments, critical, warnings, notices, ' .
                'ignored, durationms, dbqueries, peakmemory, retrycount, startedat, finishedat, errormessage, timecreated, ' .
                'configsnapshot, confighash, finaldurationms, finaldbqueries'
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
                'timecreated, timestarted, timefinished, errormessage, filterjson'
        );
        $operations = [];
        foreach ($batches as $batch) {
            $operations[] = (array)$batch;
        }

        $exceptions = $DB->get_records(
            'tool_coursecompliance_exception',
            ['userid' => $userid],
            'timecreated ASC',
            'id, profileid, courseid, rulecode, component, objectid, reason, expiresat, enabled, timecreated, timemodified'
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
        $contexts = $contextlist->get_contexts();
        if (!$contexts) {
            return;
        }

        $systemcontextid = \context_system::instance()->id;
        foreach ($contexts as $context) {
            if ((int)$context->id === (int)$systemcontextid) {
                self::delete_data_for_userids([(int)$contextlist->get_user()->id]);
                return;
            }
        }
    }

    /**
     * Delete personal data for multiple users in one context.
     *
     * @param approved_userlist $userlist Approved users in a context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        self::delete_data_for_userids(array_map('intval', $userlist->get_userids()));
    }

    /**
     * Delete plugin data owned by the supplied user ids.
     *
     * @param array $userids User ids.
     */
    private static function delete_data_for_userids(array $userids): void {
        global $DB;

        $userids = array_values(array_unique(array_filter($userids, static fn(int $userid): bool => $userid > 0)));
        if (!$userids) {
            return;
        }

        [$userinsql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ccuserid');

        $scanids = $DB->get_fieldset_select(
            'tool_coursecompliance_scan',
            'id',
            "userid $userinsql",
            $userparams
        );
        if ($scanids) {
            [$scaninsql, $scanparams] = $DB->get_in_or_equal($scanids, SQL_PARAMS_NAMED, 'ccscan');
            $DB->delete_records_select('tool_coursecompliance_result', "scanid $scaninsql", $scanparams);
            $DB->delete_records_select('tool_coursecompliance_assessment', "scanid $scaninsql", $scanparams);
            $DB->delete_records_select('tool_coursecompliance_course', "scanid $scaninsql", $scanparams);
            $DB->delete_records_select('tool_coursecompliance_qissue', "scanid $scaninsql", $scanparams);
            $DB->delete_records_select('tool_coursecompliance_scan', "id $scaninsql", $scanparams);
        }

        $DB->delete_records_select('tool_coursecompliance_exception', "userid $userinsql", $userparams);

        $batchids = $DB->get_fieldset_select(
            'tool_coursecompliance_act_batch',
            'id',
            "userid $userinsql",
            $userparams
        );
        if ($batchids) {
            [$batchinsql, $batchparams] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED, 'ccbatch');
            $DB->delete_records_select('tool_coursecompliance_act_change', "batchid $batchinsql", $batchparams);
            $DB->delete_records_select('tool_coursecompliance_session_issue', "batchid $batchinsql", $batchparams);
            $DB->delete_records_select('tool_coursecompliance_act_batch', "id $batchinsql", $batchparams);
        }
    }

    /**
     * Delete all stored scan and operation data.
     */
    private static function delete_all_scans(): void {
        global $DB;

        $DB->delete_records('tool_coursecompliance_result');
        $DB->delete_records('tool_coursecompliance_assessment');
        $DB->delete_records('tool_coursecompliance_course');
        $DB->delete_records('tool_coursecompliance_qissue');
        $DB->delete_records('tool_coursecompliance_scan');
        $DB->delete_records('tool_coursecompliance_exception');
        $DB->delete_records('tool_coursecompliance_act_change');
        $DB->delete_records('tool_coursecompliance_session_issue');
        $DB->delete_records('tool_coursecompliance_act_batch');
    }
}
