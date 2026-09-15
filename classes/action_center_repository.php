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
 * SQL-backed Action Center queries.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Provides paged current-issue queries without loading every result into PHP.
 */
final class action_center_repository {
    /**
     * Return one page of current issues.
     *
     * @param string $severity Severity filter.
     * @param string $search Search text.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return \stdClass[]
     */
    public static function page(
        string $severity,
        string $search,
        int $page,
        int $perpage
    ): array {
        global $DB;

        [$where, $params] = self::where($severity, $search);
        $sql = "SELECT r.*, s.profileid, s.categoryid,
                       COALESCE(NULLIF(s.finishedat, 0), s.timecreated) AS scantime,
                       c.fullname, c.shortname, p.name AS profilename
                  FROM {tool_coursecompliance_result} r
                  JOIN {tool_coursecompliance_scan} s ON s.id = r.scanid
                  JOIN {course} c ON c.id = r.courseid
             LEFT JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
                 WHERE $where
              ORDER BY CASE r.severity
                           WHEN 'critical' THEN 1
                           WHEN 'warning' THEN 2
                           WHEN 'notice' THEN 3
                           ELSE 4
                       END,
                       c.fullname,
                       r.id";
        return array_values($DB->get_records_sql(
            $sql,
            $params,
            max(0, $page) * $perpage,
            $perpage
        ));
    }

    /**
     * Count current issues matching filters.
     *
     * @param string $severity Severity filter.
     * @param string $search Search text.
     * @return int
     */
    public static function count(string $severity = '', string $search = ''): int {
        global $DB;

        [$where, $params] = self::where($severity, $search);
        $sql = "SELECT COUNT(1)
                  FROM {tool_coursecompliance_result} r
                  JOIN {tool_coursecompliance_scan} s ON s.id = r.scanid
                  JOIN {course} c ON c.id = r.courseid
                 WHERE $where";
        return (int)$DB->count_records_sql($sql, $params);
    }

    /**
     * Count current issues by severity in one query.
     *
     * @return array<string, int>
     */
    public static function severity_counts(): array {
        global $DB;

        [$where, $params] = self::where('', '');
        $sql = "SELECT r.severity, COUNT(1) AS issuecount
                  FROM {tool_coursecompliance_result} r
                  JOIN {tool_coursecompliance_scan} s ON s.id = r.scanid
                  JOIN {course} c ON c.id = r.courseid
                 WHERE $where
              GROUP BY r.severity";
        $records = $DB->get_records_sql($sql, $params);
        $counts = ['critical' => 0, 'warning' => 0, 'notice' => 0];
        foreach ($records as $record) {
            if (array_key_exists((string)$record->severity, $counts)) {
                $counts[(string)$record->severity] = (int)$record->issuecount;
            }
        }
        return $counts;
    }

    /**
     * Shared WHERE clause that identifies the newest completed scan per course/profile.
     *
     * @param string $severity Severity filter.
     * @param string $search Search text.
     * @return array{0:string,1:array}
     */
    private static function where(string $severity, string $search): array {
        global $DB;

        $conditions = [
            "s.status = :ccstatus",
            "s.parentid = 0",
            "s.scantype <> :ccbatch",
            "NOT EXISTS (
                SELECT 1
                  FROM {tool_coursecompliance_scan} newer
                  JOIN {tool_coursecompliance_course} newercourse
                    ON newercourse.scanid = newer.id
                   AND newercourse.courseid = r.courseid
                 WHERE newer.status = :ccnewstatus
                   AND newer.parentid = 0
                   AND newer.scantype <> :ccnewbatch
                   AND newer.profileid = s.profileid
                   AND newer.id > s.id
            )",
        ];
        $params = [
            'ccstatus' => 'complete',
            'ccbatch' => 'batch',
            'ccnewstatus' => 'complete',
            'ccnewbatch' => 'batch',
        ];
        if ($severity !== '') {
            $conditions[] = 'r.severity = :ccseverity';
            $params['ccseverity'] = $severity;
        }
        if ($search !== '') {
            $pattern = '%' . $DB->sql_like_escape($search) . '%';
            $searchparts = [];
            $searchfields = [
                'c.fullname' => 'ccsearchfullname',
                'c.shortname' => 'ccsearchshortname',
                'r.message' => 'ccsearchmessage',
                'r.rulecode' => 'ccsearchrule',
            ];
            foreach ($searchfields as $field => $paramname) {
                $searchparts[] = $DB->sql_like($field, ':' . $paramname, false, false);
                $params[$paramname] = $pattern;
            }
            $conditions[] = '(' . implode(' OR ', $searchparts) . ')';
        }
        return [implode("\n AND ", $conditions), $params];
    }
}
