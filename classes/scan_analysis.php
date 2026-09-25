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
 * Cross-scan analysis helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Builds deltas, readiness matrices and current-action views from scan data.
 */
final class scan_analysis {
    /** Severity weight used by matrix cells. */
    private const SEVERITY_WEIGHT = [
        'ready' => 0,
        'notice' => 1,
        'warning' => 2,
        'critical' => 3,
    ];

    /**
     * Find the previous comparable completed scan.
     *
     * @param \stdClass $scan Current scan.
     * @return \stdClass|null
     */
    public static function previous_scan(\stdClass $scan): ?\stdClass {
        global $DB;

        $params = [
            'status' => 'complete',
            'profileid' => (int)$scan->profileid,
            'categoryid' => (int)$scan->categoryid,
            'currentid' => (int)$scan->id,
        ];
        $sql = "SELECT *
                  FROM {tool_coursecompliance_scan}
                 WHERE status = :status
                   AND profileid = :profileid
                   AND categoryid = :categoryid
                   AND id < :currentid
              ORDER BY id DESC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Compare a scan with its predecessor.
     *
     * @param int $scanid Scan id.
     * @return array<string, mixed>
     */
    public static function delta(int $scanid): array {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $previous = self::previous_scan($scan);
        $currentresults = $DB->get_records('tool_coursecompliance_result', ['scanid' => $scanid]);
        $currentsignatures = [];
        foreach ($currentresults as $result) {
            $currentsignatures[self::result_signature($result)] = $result;
        }

        $scope = json_decode((string)($scan->scopejson ?? ''), true);
        $scopecourseids = is_array($scope) && is_array($scope['courseids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $scope['courseids']))))
            : [];
        $scopelookup = $scopecourseids ? array_fill_keys($scopecourseids, true) : [];

        $previoussignatures = [];
        if ($previous) {
            foreach ($DB->get_records('tool_coursecompliance_result', ['scanid' => $previous->id]) as $result) {
                if ($scopelookup && !isset($scopelookup[(int)$result->courseid])) {
                    continue;
                }
                $previoussignatures[self::result_signature($result)] = $result;
            }
        }

        $new = array_diff_key($currentsignatures, $previoussignatures);
        $resolved = array_diff_key($previoussignatures, $currentsignatures);
        $currentcourses = self::course_snapshots($scanid);
        $previouscourses = $previous ? self::course_snapshots((int)$previous->id) : [];
        if ($scopelookup) {
            $previouscourses = array_filter(
                $previouscourses,
                static fn ($state, $courseid): bool => isset($scopelookup[(int)$courseid]),
                ARRAY_FILTER_USE_BOTH
            );
        }
        $becameready = 0;
        $lostready = 0;
        foreach ($currentcourses as $courseid => $state) {
            $previousstate = $previouscourses[$courseid] ?? null;
            if (!$previousstate) {
                continue;
            }
            if ($state->status === 'ready' && $previousstate->status !== 'ready') {
                $becameready++;
            } else if ($state->status !== 'ready' && $previousstate->status === 'ready') {
                $lostready++;
            }
        }

        return [
            'previous' => $previous,
            'new' => $new,
            'resolved' => $resolved,
            'newcount' => count($new),
            'resolvedcount' => count($resolved),
            'becameready' => $becameready,
            'lostready' => $lostready,
        ];
    }

    /**
     * Return per-course scan states, with a fallback for older scans.
     *
     * @param int $scanid Scan id.
     * @return array<int, \stdClass>
     */
    public static function course_snapshots(int $scanid): array {
        global $DB;

        $records = $DB->get_records('tool_coursecompliance_course', ['scanid' => $scanid], '', '*');
        if ($records) {
            $out = [];
            foreach ($records as $record) {
                $out[(int)$record->courseid] = $record;
            }
            return $out;
        }

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $counts = $DB->get_records_sql(
            "SELECT courseid,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical,
                    SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warnings,
                    SUM(CASE WHEN severity = 'notice' THEN 1 ELSE 0 END) AS notices
               FROM {tool_coursecompliance_result}
              WHERE scanid = :scanid
           GROUP BY courseid",
            ['scanid' => $scanid]
        );
        $out = [];
        foreach ($counts as $record) {
            $critical = (int)$record->critical;
            $warnings = (int)$record->warnings;
            $notices = (int)$record->notices;
            $out[(int)$record->courseid] = (object)[
                'scanid' => $scanid,
                'courseid' => (int)$record->courseid,
                'critical' => $critical,
                'warnings' => $warnings,
                'notices' => $notices,
                'ignored' => 0,
                'score' => max(0, 100 - ($critical * 25) - ($warnings * 8) - ($notices * 2)),
                'status' => $critical ? 'critical' : ($warnings ? 'warning' : ($notices ? 'notice' : 'ready')),
            ];
        }
        return $out;
    }

    /**
     * Apply newer targeted rechecks to a baseline course snapshot.
     *
     * @param int $scanid Baseline scan id.
     * @return array<int, \stdClass>
     */
    public static function composite_course_snapshots(int $scanid): array {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $states = self::course_snapshots($scanid);
        foreach (self::newer_rechecks($scan) as $recheck) {
            foreach (self::course_snapshots((int)$recheck->id) as $courseid => $state) {
                $states[$courseid] = $state;
            }
        }
        return $states;
    }

    /**
     * Apply newer targeted rechecks to baseline result rows.
     *
     * @param int $scanid Baseline scan id.
     * @return \stdClass[]
     */
    public static function composite_results(int $scanid): array {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $results = [];
        foreach ($DB->get_records('tool_coursecompliance_result', ['scanid' => $scanid]) as $result) {
            $results[self::result_signature($result)] = $result;
        }
        foreach (self::newer_rechecks($scan) as $recheck) {
            $scope = json_decode((string)($recheck->scopejson ?? ''), true);
            $courseids = is_array($scope) && is_array($scope['courseids'] ?? null)
                ? array_values(array_unique(array_filter(array_map('intval', $scope['courseids']))))
                : [];
            if (!$courseids) {
                continue;
            }
            $lookup = array_fill_keys($courseids, true);
            foreach ($results as $signature => $result) {
                if (isset($lookup[(int)$result->courseid])) {
                    unset($results[$signature]);
                }
            }
            foreach ($DB->get_records('tool_coursecompliance_result', ['scanid' => $recheck->id]) as $result) {
                $results[self::result_signature($result)] = $result;
            }
        }
        return array_values($results);
    }

    /**
     * Apply newer targeted rechecks to assessment readiness rows.
     *
     * @param int $scanid Baseline scan id.
     * @return array<string, \stdClass> Keyed by courseid:assessmenttype.
     */
    public static function composite_assessments(int $scanid): array {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $states = [];
        foreach ($DB->get_records('tool_coursecompliance_assessment', ['scanid' => $scanid]) as $state) {
            $states[(int)$state->courseid . ':' . $state->assessmenttype] = $state;
        }
        foreach (self::newer_rechecks($scan) as $recheck) {
            $scope = json_decode((string)($recheck->scopejson ?? ''), true);
            $courseids = is_array($scope) && is_array($scope['courseids'] ?? null)
                ? array_values(array_unique(array_filter(array_map('intval', $scope['courseids']))))
                : [];
            if (!$courseids) {
                continue;
            }
            $lookup = array_fill_keys($courseids, true);
            foreach ($states as $key => $state) {
                if (isset($lookup[(int)$state->courseid])) {
                    unset($states[$key]);
                }
            }
            foreach ($DB->get_records('tool_coursecompliance_assessment', ['scanid' => $recheck->id]) as $state) {
                $states[(int)$state->courseid . ':' . $state->assessmenttype] = $state;
            }
        }
        return $states;
    }

    /**
     * Build a course-by-domain readiness matrix.
     *
     * @param int $scanid Scan id.
     * @return array<int, array<string, mixed>>
     */
    public static function readiness_matrix(int $scanid): array {
        global $DB;

        $states = self::composite_course_snapshots($scanid);
        if (!$states) {
            return [];
        }
        $courseids = array_keys($states);
        $courses = $DB->get_records_list('course', 'id', $courseids, 'fullname ASC', 'id,fullname,shortname');
        $rows = [];
        $dimensions = ['course', 'exam', 'resit1', 'resit2', 'questionbank', 'availability', 'gradebook', 'completion'];
        foreach ($courses as $course) {
            $cells = array_fill_keys($dimensions, 'ready');
            $rows[(int)$course->id] = [
                'course' => $course,
                'state' => $states[(int)$course->id] ?? null,
                'cells' => $cells,
            ];
        }

        $results = self::composite_results($scanid);
        foreach ($results as $result) {
            $courseid = (int)$result->courseid;
            if (!isset($rows[$courseid])) {
                continue;
            }
            foreach (self::result_dimensions($result) as $dimension) {
                $current = $rows[$courseid]['cells'][$dimension] ?? 'ready';
                $candidate = (string)$result->severity;
                if ((self::SEVERITY_WEIGHT[$candidate] ?? 0) > (self::SEVERITY_WEIGHT[$current] ?? 0)) {
                    $rows[$courseid]['cells'][$dimension] = $candidate;
                }
            }
        }
        return $rows;
    }

    /**
     * Return current issues from the newest completed scan that actually checked each course/profile pair.
     *
     * A newer targeted or manually selected audit supersedes older findings for the same course and profile,
     * even when the newer scan is clean. This keeps the Action Center aligned with the course-level indicator.
     *
     * @param int $limit Maximum number of result rows.
     * @return \stdClass[]
     */
    public static function current_action_results(int $limit = 1000): array {
        global $DB;

        $rowkeysql = $DB->sql_concat('cs.courseid', "'-'", 's.profileid');
        $latestsql = "SELECT $rowkeysql AS rowkey,
                             cs.courseid, s.profileid, MAX(s.id) AS scanid
                        FROM {tool_coursecompliance_course} cs
                        JOIN {tool_coursecompliance_scan} s ON s.id = cs.scanid
                       WHERE s.status = :status
                         AND s.parentid = 0
                         AND s.scantype <> :batchtype
                         AND EXISTS (SELECT 1 FROM {tool_coursecompliance_profile} cp
                                      WHERE cp.id = s.profileid AND cp.enabled = 1)
                    GROUP BY cs.courseid, s.profileid";
        $latestrows = $DB->get_records_sql($latestsql, [
            'status' => 'complete',
            'batchtype' => 'batch',
        ]);
        if (!$latestrows) {
            return [];
        }

        $latest = [];
        $scanids = [];
        $profileids = [];
        $courseids = [];
        foreach ($latestrows as $row) {
            $key = (int)$row->courseid . ':' . (int)$row->profileid;
            $latest[$key] = (int)$row->scanid;
            $scanids[(int)$row->scanid] = true;
            $profileids[(int)$row->profileid] = true;
            $courseids[(int)$row->courseid] = true;
        }

        [$scaninsql, $scanparams] = $DB->get_in_or_equal(array_keys($scanids), SQL_PARAMS_NAMED, 'currentscan');
        $scanrecords = $DB->get_records_select(
            'tool_coursecompliance_scan',
            "id $scaninsql",
            $scanparams,
            '',
            'id,profileid,categoryid,timecreated,finishedat'
        );
        $profilerecords = $DB->get_records_list(
            'tool_coursecompliance_profile',
            'id',
            array_keys($profileids),
            '',
            'id,name'
        );
        $courses = $DB->get_records_list(
            'course',
            'id',
            array_keys($courseids),
            '',
            'id,fullname,shortname'
        );
        $results = $DB->get_records_select(
            'tool_coursecompliance_result',
            "scanid $scaninsql",
            $scanparams
        );

        $out = [];
        foreach ($results as $result) {
            $scan = $scanrecords[(int)$result->scanid] ?? null;
            $course = $courses[(int)$result->courseid] ?? null;
            if (!$scan || !$course) {
                continue;
            }
            $key = (int)$result->courseid . ':' . (int)$scan->profileid;
            if (($latest[$key] ?? 0) !== (int)$result->scanid) {
                continue;
            }
            $result->profileid = (int)$scan->profileid;
            $result->categoryid = (int)$scan->categoryid;
            $result->scantime = (int)($scan->finishedat ?: $scan->timecreated);
            $result->fullname = (string)$course->fullname;
            $result->shortname = (string)$course->shortname;
            $result->profilename = isset($profilerecords[(int)$scan->profileid])
                ? (string)$profilerecords[(int)$scan->profileid]->name
                : '';
            $out[] = $result;
        }

        usort($out, static function ($left, $right): int {
            $weights = ['critical' => 0, 'warning' => 1, 'notice' => 2];
            $severity = ($weights[$left->severity] ?? 3) <=> ($weights[$right->severity] ?? 3);
            if ($severity !== 0) {
                return $severity;
            }
            return strcasecmp((string)$left->fullname, (string)$right->fullname);
        });
        return array_slice($out, 0, max(0, $limit));
    }

    /**
     * Return the newest completed readiness snapshot for each requested course.
     *
     * @param int[] $courseids Course ids.
     * @param int $profileid Optional profile filter, 0 for any profile.
     * @return array<int, \stdClass> Keyed by course id.
     */
    public static function latest_course_states(array $courseids, int $profileid = 0): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!$courseids) {
            return [];
        }
        [$courseinsql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'statecourse');
        $params['status'] = 'complete';
        $profilewhere = '';
        if ($profileid > 0) {
            $profilewhere = ' AND s.profileid = :profileid';
            $params['profileid'] = $profileid;
        }
        $sql = "SELECT cs.*, s.profileid, s.finishedat, s.timecreated AS scantimecreated,
                       p.name AS profilename
                  FROM {tool_coursecompliance_course} cs
                  JOIN {tool_coursecompliance_scan} s ON s.id = cs.scanid
             LEFT JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
                 WHERE cs.courseid $courseinsql
                   AND s.status = :status
                   AND s.parentid = 0
                       $profilewhere
              ORDER BY s.id DESC";
        $records = $DB->get_records_sql($sql, $params);
        $states = [];
        foreach ($records as $record) {
            $courseid = (int)$record->courseid;
            if (isset($states[$courseid])) {
                continue;
            }
            $record->scandate = (int)($record->finishedat ?: $record->scantimecreated);
            $states[$courseid] = $record;
        }
        return $states;
    }

    /**
     * Build a stable identity for the same issue between scans.
     *
     * @param \stdClass $result Result row.
     * @return string
     */
    public static function result_signature(\stdClass $result): string {
        return implode('|', [
            (int)$result->courseid,
            (string)$result->component,
            (string)$result->assessmenttype,
            (string)$result->rulecode,
            (int)$result->objectid,
        ]);
    }

    /**
     * Find completed targeted rechecks newer than the supplied scan.
     *
     * @param \stdClass $scan Baseline scan.
     * @return \stdClass[]
     */
    private static function newer_rechecks(\stdClass $scan): array {
        global $DB;

        $sql = "SELECT *
                  FROM {tool_coursecompliance_scan}
                 WHERE status = :status
                   AND scantype = :scantype
                   AND profileid = :profileid
                   AND categoryid = :categoryid
                   AND id > :scanid
              ORDER BY id ASC";
        return array_values($DB->get_records_sql($sql, [
            'status' => 'complete',
            'scantype' => 'recheck',
            'profileid' => (int)$scan->profileid,
            'categoryid' => (int)$scan->categoryid,
            'scanid' => (int)$scan->id,
        ]));
    }

    /**
     * Determine the matrix domains affected by a result.
     *
     * @param \stdClass $result Result row.
     * @return string[]
     */
    private static function result_dimensions(\stdClass $result): array {
        $dimensions = [];
        $rulecode = (string)$result->rulecode;
        $component = (string)$result->component;
        $assessmenttype = (string)$result->assessmenttype;

        if (in_array($assessmenttype, ['exam', 'resit1', 'resit2'], true)) {
            $dimensions[] = $assessmenttype;
        } else {
            $dimensions[] = 'course';
        }
        if (
            str_contains($rulecode, 'QUESTION')
            || str_contains($rulecode, 'RANDOM_POOL')
            || str_contains($rulecode, 'BANK_CATEGORY')
            || str_contains($rulecode, 'SUMGRADES')
            || str_contains($rulecode, 'STRUCTURE_LOADABLE')
        ) {
            $dimensions[] = 'questionbank';
        }
        if (str_contains($rulecode, 'AVAILABILITY')) {
            $dimensions[] = 'availability';
        }
        if ($component === 'gradebook' || str_contains($rulecode, 'GRADE')) {
            $dimensions[] = 'gradebook';
        }
        if ($component === 'completion' || str_contains($rulecode, 'COMPLETION')) {
            $dimensions[] = 'completion';
        }
        return array_values(array_unique($dimensions));
    }
}
