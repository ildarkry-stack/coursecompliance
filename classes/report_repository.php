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
 * Bounded report queries.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/** Bounded-memory access to audit report rows. */
final class report_repository {
    /**
     * Return filtered rows and the full matching count.
     *
     * @param string $where Internal SQL predicate.
     * @param array $params Bound parameters.
     * @param string $kind Semantic filter.
     * @param array $config Frozen audit configuration.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array Rows and total count.
     */
    public static function page(string $where, array $params, string $kind, array $config, int $page, int $perpage): array {
        global $DB;
        $from = "FROM {tool_coursecompliance_result} r
                  JOIN {course} c ON c.id = r.courseid";
        $select = "SELECT r.*, c.fullname, c.shortname, cm.id AS cmid $from
             LEFT JOIN {modules} m ON m.name = r.component
             LEFT JOIN {course_modules} cm ON cm.course = r.courseid
                       AND cm.module = m.id AND cm.instance = r.objectid
                 WHERE $where
              ORDER BY CASE r.severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END,
                       c.fullname, r.rulecode, r.id";
        $perpage = max(1, min(1000, $perpage));
        $offset = max(0, $page) * $perpage;
        if (!in_array($kind, ['missing', 'mismatch', 'autofix'], true)) {
            $total = $DB->count_records_sql("SELECT COUNT(1) $from WHERE $where", $params);
            return [$DB->get_records_sql($select, $params, $offset, $perpage), (int)$total];
        }
        // Preserve the exact PHP rule semantics for historical rows. Never collect the entire result.
        $rows = [];
        $total = 0;
        $records = $DB->get_recordset_sql($select, $params);
        try {
            foreach ($records as $record) {
                $matches = match ($kind) {
                    'missing' => report_helper::is_missing($record),
                    'mismatch' => !report_helper::is_missing($record),
                    'autofix' => autofix_manager::is_fixable($record, $config),
                };
                if (!$matches) {
                    continue;
                }
                if ($total >= $offset && count($rows) < $perpage) {
                    $rows[$record->id] = $record;
                }
                $total++;
            }
        } finally {
            $records->close();
        }
        return [$rows, $total];
    }

    /**
     * Summarise a scan without keeping its violations or all course names in memory.
     *
     * @param int $scanid Audit id.
     * @param array $config Audit configuration.
     * @return array Summary values.
     */
    public static function summary(int $scanid, array $config): array {
        global $DB;
        $missing = [];
        $affected = 0;
        $lastcourse = 0;
        $mismatch = 0;
        $fixable = 0;
        $records = $DB->get_recordset_sql(
            "SELECT r.*, c.fullname
               FROM {tool_coursecompliance_result} r JOIN {course} c ON c.id = r.courseid
              WHERE r.scanid = :scanid ORDER BY r.courseid, r.id",
            ['scanid' => $scanid]
        );
        try {
            foreach ($records as $record) {
                if ((int)$record->courseid !== $lastcourse) {
                    $affected++;
                    $lastcourse = (int)$record->courseid;
                }
                $fixable += (int)autofix_manager::is_fixable($record, $config);
                if (!report_helper::is_missing($record)) {
                    $mismatch++;
                    continue;
                }
                $label = report_helper::missing_description($record, $config);
                if (!isset($missing[$label])) {
                    $missing[$label] = ['count' => 0, 'courses' => [], 'coursecount' => 0, 'lastcourse' => 0];
                }
                $item = &$missing[$label];
                $item['count']++;
                if ($item['lastcourse'] !== (int)$record->courseid) {
                    $item['coursecount']++;
                    $item['lastcourse'] = (int)$record->courseid;
                    if (count($item['courses']) < 5) {
                        $item['courses'][] = format_string($record->fullname);
                    }
                }
                unset($item);
            }
        } finally {
            $records->close();
        }
        return [$missing, $affected, $mismatch, $fixable];
    }
}
