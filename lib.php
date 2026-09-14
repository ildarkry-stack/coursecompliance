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
 * Library callbacks for tool_coursecompliance.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a problem indicator to course navigation for site administrators.
 *
 * @param navigation_node $parentnode Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function tool_coursecompliance_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
): void {
    if (!is_siteadmin()) {
        return;
    }

    $summary = \tool_coursecompliance\course_indicator::get_for_course(
        (int)$course->id,
        (int)$course->category
    );
    if (!$summary) {
        return;
    }

    $url = new moodle_url('/admin/tool/coursecompliance/course_issues.php', [
        'courseid' => (int)$course->id,
    ]);
    $parentnode->add(
        get_string('courseindicator_nav', 'tool_coursecompliance', $summary->total),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'tool_coursecompliance_courseissues',
        new pix_icon('i/warning', '')
    );
}
