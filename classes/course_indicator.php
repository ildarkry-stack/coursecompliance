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
 * Course-level compliance indicator.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Resolves the latest applicable audit problems for a course.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_indicator {
    /** @var array<int, null|\stdClass> Request-local result cache. */
    private static array $cache = [];

    /**
     * Return the latest applicable problem summary for a course.
     *
     * A category audit applies to courses in the selected category and all of its
     * subcategories. Therefore the newest completed scan whose category is in the
     * course category path is treated as the current state. Old problems are not
     * shown when a newer applicable scan completed successfully without problems.
     *
     * @param int $courseid Course id.
     * @param int $categoryid Current course category id.
     * @return null|\stdClass Problem summary, or null when the latest audit is clean/unavailable.
     */
    public static function get_for_course(int $courseid, int $categoryid): ?\stdClass {
        global $DB;

        if (array_key_exists($courseid, self::$cache)) {
            return self::$cache[$courseid];
        }
        if ($courseid <= 0 || $courseid === SITEID || $categoryid <= 0) {
            self::$cache[$courseid] = null;
            return null;
        }

        $path = (string)$DB->get_field('course_categories', 'path', ['id' => $categoryid]);
        $categoryids = array_values(array_unique(array_filter(
            array_map('intval', explode('/', $path)),
            static fn(int $id): bool => $id > 0
        )));
        if (!in_array($categoryid, $categoryids, true)) {
            $categoryids[] = $categoryid;
        }
        if (!$categoryids) {
            self::$cache[$courseid] = null;
            return null;
        }

        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'indicatorcat');
        $params['scanstatus'] = 'complete';
        $sql = "SELECT s.id, s.profileid, s.finishedat, s.timecreated, p.name AS profilename
                  FROM {tool_coursecompliance_scan} s
                  JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
                 WHERE s.categoryid $insql
                       AND s.status = :scanstatus
              ORDER BY s.finishedat DESC, s.id DESC";
        $scans = $DB->get_records_sql($sql, $params, 0, 1);
        $scan = $scans ? reset($scans) : false;
        if (!$scan) {
            self::$cache[$courseid] = null;
            return null;
        }

        $countsql = "SELECT severity, COUNT(1) AS issuecount
                       FROM {tool_coursecompliance_result}
                      WHERE scanid = :scanid AND courseid = :courseid
                   GROUP BY severity";
        $counts = $DB->get_records_sql($countsql, [
            'scanid' => (int)$scan->id,
            'courseid' => $courseid,
        ]);

        $summary = (object)[
            'scanid' => (int)$scan->id,
            'profileid' => (int)$scan->profileid,
            'profilename' => (string)$scan->profilename,
            'scandate' => (int)($scan->finishedat ?: $scan->timecreated),
            'critical' => 0,
            'warnings' => 0,
            'notices' => 0,
            'total' => 0,
        ];
        foreach ($counts as $count) {
            $value = (int)$count->issuecount;
            if ($count->severity === 'critical') {
                $summary->critical = $value;
            } elseif ($count->severity === 'warning') {
                $summary->warnings = $value;
            } elseif ($count->severity === 'notice') {
                $summary->notices = $value;
            }
        }
        $summary->total = $summary->critical + $summary->warnings + $summary->notices;
        self::$cache[$courseid] = $summary->total > 0 ? $summary : null;
        return self::$cache[$courseid];
    }
}
