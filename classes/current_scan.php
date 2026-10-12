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
 * Current published audit predicates.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Shared publication rules for live compliance dashboards.
 */
final class current_scan {
    /**
     * Build an internal-only predicate for the latest published course/profile scan.
     *
     * @param string $alias Scan SQL alias, never user input.
     * @param string $course Course SQL expression, never user input.
     * @return string SQL predicate with no bound parameters.
     */
    public static function predicate(string $alias, string $course): string {
        return "$alias.status = 'complete' AND $alias.parentid = 0 AND $alias.scantype <> 'batch'
            AND EXISTS (SELECT 1 FROM {tool_coursecompliance_profile} cp
                         WHERE cp.id = $alias.profileid AND cp.enabled = 1)
            AND NOT EXISTS (
                SELECT 1 FROM {tool_coursecompliance_scan} newer
                JOIN {tool_coursecompliance_course} nc ON nc.scanid = newer.id
                 WHERE nc.courseid = $course AND newer.profileid = $alias.profileid
                   AND newer.status = 'complete' AND newer.parentid = 0
                   AND newer.scantype <> 'batch' AND newer.id > $alias.id
            )";
    }
}
