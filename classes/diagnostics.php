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
 * Read-only environment diagnostics.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Collect support and runtime diagnostics without changing site state.
 */
final class diagnostics {
    /** Supported Moodle branches. */
    private const MIN_BRANCH = 500;

    /** Latest Moodle branch verified by this release. */
    private const MAX_BRANCH = 502;

    /**
     * Build a diagnostics snapshot.
     *
     * @return array
     */
    public static function snapshot(): array {
        global $CFG, $DB;

        $branch = (int)($CFG->branch ?? 0);
        $dbfamily = (string)$DB->get_dbfamily();
        $minimumphp = self::minimum_php_for_branch($branch);
        $apisavailable = self::required_apis_available();
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('tool_coursecompliance');
        $pluginrelease = $plugininfo && $plugininfo->release ? (string)$plugininfo->release : '';
        $now = time();
        $stalledcutoff = $now - (2 * HOURSECS);

        $pendingtasks = (int)$DB->count_records('task_adhoc', ['component' => 'tool_coursecompliance']);
        $oldesttask = (int)$DB->get_field_sql(
            "SELECT MIN(nextruntime)
               FROM {task_adhoc}
              WHERE component = :component",
            ['component' => 'tool_coursecompliance']
        );
        $failedscans = (int)$DB->count_records('tool_coursecompliance_scan', [
            'parentid' => 0,
            'status' => 'failed',
        ]);
        $stalledscans = (int)$DB->count_records_select(
            'tool_coursecompliance_scan',
            'parentid = 0 AND status IN (:queued, :running) AND timecreated < :cutoff',
            [
                'queued' => 'queued',
                'running' => 'running',
                'cutoff' => $stalledcutoff,
            ]
        );
        $lastcompleted = (int)$DB->get_field_sql(
            "SELECT MAX(finishedat)
               FROM {tool_coursecompliance_scan}
              WHERE parentid = 0
                AND status = :status",
            ['status' => 'complete']
        );

        $checks = [
            'moodle' => [
                'ok' => self::is_supported_branch($branch),
                'value' => (string)($CFG->release ?? $branch),
            ],
            'php' => [
                'ok' => version_compare(PHP_VERSION, $minimumphp, '>='),
                'value' => PHP_VERSION,
                'minimum' => $minimumphp,
            ],
            'database' => [
                'ok' => in_array($dbfamily, ['mysql', 'postgres'], true),
                'value' => $dbfamily,
            ],
            'apis' => [
                'ok' => $apisavailable,
                'value' => $apisavailable ? 'available' : 'missing',
            ],
        ];

        return [
            'branch' => $branch,
            'pluginrelease' => $pluginrelease,
            'checks' => $checks,
            'pendingtasks' => $pendingtasks,
            'oldesttask' => $oldesttask,
            'failedscans' => $failedscans,
            'stalledscans' => $stalledscans,
            'lastcompleted' => $lastcompleted,
        ];
    }

    /**
     * Whether this release explicitly supports a Moodle branch.
     *
     * @param int $branch Moodle branch, for example 500 or 502.
     * @return bool
     */
    public static function is_supported_branch(int $branch): bool {
        return $branch >= self::MIN_BRANCH && $branch <= self::MAX_BRANCH;
    }

    /**
     * Get the minimum PHP version expected for a supported Moodle branch.
     *
     * @param int $branch Moodle branch.
     * @return string
     */
    public static function minimum_php_for_branch(int $branch): string {
        return $branch >= 502 ? '8.3.0' : '8.2.0';
    }

    /**
     * Check APIs used by the scanner and background processing layer.
     *
     * @return bool
     */
    public static function required_apis_available(): bool {
        return class_exists(\core\task\adhoc_task::class)
            && class_exists(\core\message\message::class)
            && class_exists(\mod_quiz\quiz_settings::class)
            && class_exists(\mod_quiz\question\bank\qbank_helper::class)
            && class_exists(\core_question\local\bank\random_question_loader::class);
    }
}
