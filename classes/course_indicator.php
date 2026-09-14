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
 * Resolves current audit problems for a course.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_indicator {
    /** @var array<int, null|\stdClass> Request-local result cache. */
    private static array $cache = [];

    /**
     * Return the current problem summary for a course.
     *
     * The latest completed scan of every enabled profile that applies to the
     * course category is considered. This prevents a newer scan of another
     * profile from hiding still-current problems found by a different profile.
     *
     * @param int $courseid Course id.
     * @param int $categoryid Current course category id.
     * @return null|\stdClass Problem summary, or null when all current audits are clean/unavailable.
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
        $params['profileenabled'] = 1;
        $latestsql = "SELECT s.profileid, MAX(s.id) AS scanid
                       FROM {tool_coursecompliance_scan} s
                       JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
                      WHERE s.categoryid $insql
                            AND s.status = :scanstatus
                            AND p.enabled = :profileenabled
                   GROUP BY s.profileid";
        $latestrows = $DB->get_records_sql($latestsql, $params);
        $scanids = array_values(array_filter(array_map(
            static fn(\stdClass $row): int => (int)$row->scanid,
            $latestrows
        )));
        if (!$scanids) {
            self::$cache[$courseid] = null;
            return null;
        }

        [$scaninsql, $scanparams] = $DB->get_in_or_equal($scanids, SQL_PARAMS_NAMED, 'indicatorscan');
        $scansql = "SELECT s.id, s.profileid, s.finishedat, s.timecreated, p.name AS profilename
                      FROM {tool_coursecompliance_scan} s
                      JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
                     WHERE s.id $scaninsql";
        $scans = $DB->get_records_sql($scansql, $scanparams);

        $countparams = $scanparams;
        $countparams['courseid'] = $courseid;
        $rowkeysql = $DB->sql_concat('r.scanid', "'-'", 'r.severity');
        $countsql = "SELECT $rowkeysql AS rowkey,
                            r.scanid, r.severity, COUNT(1) AS issuecount
                       FROM {tool_coursecompliance_result} r
                      WHERE r.scanid $scaninsql AND r.courseid = :courseid
                   GROUP BY r.scanid, r.severity";
        $counts = $DB->get_records_sql($countsql, $countparams);

        $byscan = [];
        foreach ($counts as $count) {
            $scanid = (int)$count->scanid;
            if (!isset($byscan[$scanid])) {
                $byscan[$scanid] = ['critical' => 0, 'warning' => 0, 'notice' => 0];
            }
            if (array_key_exists((string)$count->severity, $byscan[$scanid])) {
                $byscan[$scanid][(string)$count->severity] = (int)$count->issuecount;
            }
        }

        $summary = (object)[
            'scanid' => 0,
            'scanids' => [],
            'profileid' => 0,
            'profilename' => '',
            'profilecount' => 0,
            'scandate' => 0,
            'critical' => 0,
            'warnings' => 0,
            'notices' => 0,
            'total' => 0,
        ];
        $profiles = [];
        $problemdate = 0;
        foreach ($scans as $scan) {
            $scanid = (int)$scan->id;
            $scanissues = $byscan[$scanid] ?? ['critical' => 0, 'warning' => 0, 'notice' => 0];
            $total = array_sum($scanissues);
            if ($total <= 0) {
                continue;
            }

            $summary->critical += (int)$scanissues['critical'];
            $summary->warnings += (int)$scanissues['warning'];
            $summary->notices += (int)$scanissues['notice'];
            $summary->scanids[] = $scanid;
            $profiles[] = (string)$scan->profilename;
            $scandate = (int)($scan->finishedat ?: $scan->timecreated);
            if ($scandate >= $problemdate) {
                $problemdate = $scandate;
                $summary->scanid = $scanid;
                $summary->profileid = (int)$scan->profileid;
            }
        }

        $summary->total = $summary->critical + $summary->warnings + $summary->notices;
        if ($summary->total <= 0) {
            self::$cache[$courseid] = null;
            return null;
        }

        $profiles = array_values(array_unique($profiles));
        $summary->profilename = implode(', ', $profiles);
        $summary->profilecount = count($profiles);
        $summary->scandate = $problemdate;
        self::$cache[$courseid] = $summary;
        return $summary;
    }
}
