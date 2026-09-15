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
 * Scan course-scope loading.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Resolves the exact courses included in a scan.
 */
final class course_scope_loader {
    /**
     * Load courses for a scan, respecting explicit selected course ids.
     *
     * @param \stdClass $scan Scan record.
     * @param int $courselimit Legacy maximum course count, 0 for all selected courses.
     * @return \stdClass[] Courses keyed by id.
     */
    public static function load(\stdClass $scan, int $courselimit = 0): array {
        global $DB;

        $category = \core_course_category::get((int)$scan->categoryid, MUST_EXIST, true);
        $categoryids = array_merge([$category->id], $category->get_all_children_ids());
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $params['siteid'] = SITEID;
        $select = "category $insql AND id <> :siteid";

        $scope = json_decode((string)($scan->scopejson ?? ''), true);
        $scopecourseids = is_array($scope) && is_array($scope['courseids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $scope['courseids']))))
            : [];
        if ($scopecourseids) {
            [$scopeinsql, $scopeparams] = $DB->get_in_or_equal(
                $scopecourseids,
                SQL_PARAMS_NAMED,
                'scopecourse'
            );
            $select .= " AND id $scopeinsql";
            $params += $scopeparams;
            $courselimit = 0;
        } else if ($courselimit <= 0 && property_exists($scan, 'courselimit')) {
            $courselimit = (int)$scan->courselimit;
        }

        return $DB->get_records_select(
            'course',
            $select,
            $params,
            'fullname ASC, id ASC',
            '*',
            0,
            max(0, $courselimit)
        );
    }
}
