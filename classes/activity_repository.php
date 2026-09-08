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

namespace tool_coursecompliance;

use core_course_category;

/**
 * Repository for the activity operations module.
 *
 * Only supported activity types are returned. The normalized result gives the
 * UI a common timeline model while all writes remain module-specific.
 *
 * @package    tool_coursecompliance
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_repository {
    /** @var string[] Supported activity types. */
    public const SUPPORTED_MODULES = ['quiz', 'assign', 'forum'];

    /**
     * Count activities matching filters.
     *
     * @param array $filters Filters from the activity browser.
     * @return int
     */
    public function count(array $filters): int {
        global $DB;
        [$sql, $params] = $this->build_browser_sql($filters, true);
        return (int)$DB->count_records_sql($sql, $params);
    }

    /**
     * Get activities matching filters.
     *
     * @param array $filters Filters from the activity browser.
     * @param int $offset Row offset.
     * @param int $limit Number of rows.
     * @return array<int, \stdClass>
     */
    public function get(array $filters, int $offset, int $limit): array {
        global $DB;
        [$sql, $params] = $this->build_browser_sql($filters, false);
        return array_values($DB->get_records_sql($sql, $params, $offset, $limit));
    }

    /**
     * Fetch exact course modules and normalize their activity fields.
     *
     * @param int[] $cmids Course module ids.
     * @return array<int, \stdClass> Indexed by cmid.
     */
    public function get_by_cmids(array $cmids): array {
        global $DB;

        $cmids = array_values(array_unique(array_filter(array_map('intval', $cmids))));
        if (!$cmids) {
            return [];
        }
        if (count($cmids) > 500) {
            $out = [];
            foreach (array_chunk($cmids, 500) as $chunk) {
                $out += $this->get_by_cmids($chunk);
            }
            return $out;
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');
        $sql = "SELECT cm.id AS cmid, cm.course AS courseid, c.category AS categoryid, cm.instance AS instanceid,
                       cm.idnumber, cm.visible, cm.visibleoncoursepage, cm.availability,
                       m.name AS modname, c.fullname AS coursename, c.shortname AS courseshortname,
                       cs.section AS sectionnum, cs.name AS sectionname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {course} c ON c.id = cm.course
             LEFT JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.id $insql
                   AND m.name IN ('quiz', 'assign', 'forum')";
        $cms = $DB->get_records_sql($sql, $params);
        if (!$cms) {
            return [];
        }

        $grouped = ['quiz' => [], 'assign' => [], 'forum' => []];
        foreach ($cms as $cm) {
            $grouped[$cm->modname][] = (int)$cm->instanceid;
        }

        $instances = [];
        foreach ($grouped as $modname => $ids) {
            if (!$ids) {
                continue;
            }
            [$modsql, $modparams] = $DB->get_in_or_equal(array_unique($ids), SQL_PARAMS_NAMED, $modname . 'inst');
            $instances[$modname] = $DB->get_records_select($modname, "id $modsql", $modparams);
        }

        $out = [];
        foreach ($cms as $cm) {
            $instance = $instances[$cm->modname][$cm->instanceid] ?? null;
            if (!$instance) {
                continue;
            }
            $out[(int)$cm->cmid] = $this->normalise($cm, $instance);
        }
        return $out;
    }

    /**
     * Get editable date fields for an activity.
     *
     * @param string $modname Activity type.
     * @return string[]
     */
    public static function date_fields(string $modname): array {
        return match ($modname) {
            'quiz' => ['timeopen', 'timeclose'],
            'assign' => ['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'],
            'forum' => ['duedate', 'cutoffdate'],
            default => [],
        };
    }

    /**
     * Convert course module visibility to a named state.
     *
     * @param int $visible Visible flag.
     * @param int $visibleoncoursepage Visible on course page flag.
     * @return string
     */
    public static function visibility_state(int $visible, int $visibleoncoursepage): string {
        if (!$visible) {
            return 'hide';
        }
        return $visibleoncoursepage ? 'show' : 'stealth';
    }

    /**
     * Normalize one activity instance.
     *
     * @param \stdClass $cm Course module data.
     * @param \stdClass $instance Module instance.
     * @return \stdClass
     */
    private function normalise(\stdClass $cm, \stdClass $instance): \stdClass {
        $row = (object)[
            'cmid' => (int)$cm->cmid,
            'courseid' => (int)$cm->courseid,
            'categoryid' => (int)$cm->categoryid,
            'coursename' => $cm->coursename,
            'courseshortname' => $cm->courseshortname,
            'instanceid' => (int)$cm->instanceid,
            'modname' => $cm->modname,
            'activityname' => $instance->name ?? '',
            'idnumber' => $cm->idnumber ?? '',
            'visible' => (int)$cm->visible,
            'visibleoncoursepage' => (int)$cm->visibleoncoursepage,
            'visibilitystate' => self::visibility_state((int)$cm->visible, (int)$cm->visibleoncoursepage),
            'availability' => $cm->availability ?? '',
            'sectionnum' => isset($cm->sectionnum) ? (int)$cm->sectionnum : 0,
            'sectionname' => $cm->sectionname ?? '',
            'dates' => [],
        ];
        foreach (self::date_fields($row->modname) as $field) {
            $row->dates[$field] = isset($instance->{$field}) ? (int)$instance->{$field} : 0;
        }
        return $row;
    }

    /**
     * Build the browser SQL.
     *
     * @param array $filters Browser filters.
     * @param bool $count Whether to produce COUNT query.
     * @return array{0:string,1:array}
     */
    private function build_browser_sql(array $filters, bool $count): array {
        global $DB;

        $categoryid = (int)($filters['categoryid'] ?? 0);
        if (!$categoryid) {
            throw new \moodle_exception('categoryrequired', 'tool_coursecompliance');
        }
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        $categoryids = array_merge([$category->id], $category->get_all_children_ids());

        $requested = (string)($filters['modname'] ?? 'all');
        $modules = in_array($requested, self::SUPPORTED_MODULES, true) ? [$requested] : self::SUPPORTED_MODULES;
        $branches = [];
        $params = [];

        foreach ($modules as $modname) {
            [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, $modname . 'cat');
            $params += $catparams;
            $coursewhere = '';
            if (!empty($filters['courseid'])) {
                $courseparam = $modname . 'courseid';
                $coursewhere = " AND c.id = :{$courseparam}";
                $params[$courseparam] = (int)$filters['courseid'];
            }

            if ($modname === 'quiz') {
                $branches[] = "SELECT cm.id AS cmid, c.id AS courseid, c.category AS categoryid,
                                      c.fullname AS coursename, c.shortname AS courseshortname,
                                      cs.section AS sectionnum, cs.name AS sectionname,
                                      'quiz' AS modname, q.id AS instanceid, q.name AS activityname,
                                      cm.idnumber, cm.visible, cm.visibleoncoursepage, cm.availability,
                                      q.timeopen AS date1, q.timeclose AS date2, 0 AS date3, 0 AS date4
                                 FROM {course_modules} cm
                                 JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                                 JOIN {quiz} q ON q.id = cm.instance AND q.course = cm.course
                                 JOIN {course} c ON c.id = cm.course
                            LEFT JOIN {course_sections} cs ON cs.id = cm.section
                                WHERE c.category $catsql {$coursewhere}";
            } elseif ($modname === 'assign') {
                $branches[] = "SELECT cm.id AS cmid, c.id AS courseid, c.category AS categoryid,
                                      c.fullname AS coursename, c.shortname AS courseshortname,
                                      cs.section AS sectionnum, cs.name AS sectionname,
                                      'assign' AS modname, a.id AS instanceid, a.name AS activityname,
                                      cm.idnumber, cm.visible, cm.visibleoncoursepage, cm.availability,
                                      a.allowsubmissionsfromdate AS date1, a.duedate AS date2, a.cutoffdate AS date3, a.gradingduedate AS date4
                                 FROM {course_modules} cm
                                 JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                                 JOIN {assign} a ON a.id = cm.instance AND a.course = cm.course
                                 JOIN {course} c ON c.id = cm.course
                            LEFT JOIN {course_sections} cs ON cs.id = cm.section
                                WHERE c.category $catsql {$coursewhere}";
            } else {
                $branches[] = "SELECT cm.id AS cmid, c.id AS courseid, c.category AS categoryid,
                                      c.fullname AS coursename, c.shortname AS courseshortname,
                                      cs.section AS sectionnum, cs.name AS sectionname,
                                      'forum' AS modname, f.id AS instanceid, f.name AS activityname,
                                      cm.idnumber, cm.visible, cm.visibleoncoursepage, cm.availability,
                                      0 AS date1, f.duedate AS date2, f.cutoffdate AS date3, 0 AS date4
                                 FROM {course_modules} cm
                                 JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
                                 JOIN {forum} f ON f.id = cm.instance AND f.course = cm.course
                                 JOIN {course} c ON c.id = cm.course
                            LEFT JOIN {course_sections} cs ON cs.id = cm.section
                                WHERE c.category $catsql {$coursewhere}";
            }
        }

        $union = implode(' UNION ALL ', $branches);
        $outerwhere = ['1 = 1'];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $outerwhere[] = '(' . $DB->sql_like('x.activityname', ':activitysearch', false, false) .
                ' OR ' . $DB->sql_like('x.coursename', ':coursesearch', false, false) .
                ' OR ' . $DB->sql_like('x.idnumber', ':idnumbersearch', false, false) . ')';
            $like = '%' . $DB->sql_like_escape($search) . '%';
            $params['activitysearch'] = $like;
            $params['coursesearch'] = $like;
            $params['idnumbersearch'] = $like;
        }

        $sectionnum = (int)($filters['sectionnum'] ?? -1);
        if ($sectionnum >= 0) {
            $outerwhere[] = 'x.sectionnum = :sectionnum';
            $params['sectionnum'] = $sectionnum;
        }

        $visibility = (string)($filters['visibility'] ?? 'all');
        if ($visibility === 'show') {
            $outerwhere[] = 'x.visible = 1 AND x.visibleoncoursepage = 1';
        } elseif ($visibility === 'hide') {
            $outerwhere[] = 'x.visible = 0';
        } elseif ($visibility === 'stealth') {
            $outerwhere[] = 'x.visible = 1 AND x.visibleoncoursepage = 0';
        }

        $restrictions = (string)($filters['restrictions'] ?? 'all');
        if ($restrictions === 'configured') {
            $outerwhere[] = "x.availability IS NOT NULL AND x.availability <> ''";
        } elseif ($restrictions === 'none') {
            $outerwhere[] = "(x.availability IS NULL OR x.availability = '')";
        }

        $datestate = (string)($filters['datestate'] ?? 'all');
        if ($datestate === 'hasdates') {
            $outerwhere[] = '(x.date1 > 0 OR x.date2 > 0 OR x.date3 > 0 OR x.date4 > 0)';
        } elseif ($datestate === 'nodates') {
            $outerwhere[] = '(x.date1 = 0 AND x.date2 = 0 AND x.date3 = 0 AND x.date4 = 0)';
        } elseif ($datestate === 'upcoming') {
            $outerwhere[] = 'x.date1 > :nowupcoming';
            $params['nowupcoming'] = time();
        } elseif ($datestate === 'overdue') {
            $outerwhere[] = 'x.date2 > 0 AND x.date2 < :nowoverdue';
            $params['nowoverdue'] = time();
        }

        $where = implode(' AND ', $outerwhere);
        if ($count) {
            return ["SELECT COUNT(1) FROM ({$union}) x WHERE {$where}", $params];
        }

        $sql = "SELECT x.* FROM ({$union}) x
                 WHERE {$where}
              ORDER BY x.coursename ASC, x.sectionnum ASC, x.activityname ASC, x.cmid ASC";
        return [$sql, $params];
    }
}
