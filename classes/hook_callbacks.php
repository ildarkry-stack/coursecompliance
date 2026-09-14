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
 * Output hook callbacks.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Output hook callbacks.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Add an administrator-only warning to the course home page.
     *
     * The hook itself is rendered before the theme navbar, so an AMD module
     * moves the indicator into the main course content after the page is ready.
     *
     * @param before_standard_top_of_body_html_generation $hook Output hook.
     */
    public static function before_standard_top_of_body_html_generation(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE;

        if (!is_siteadmin() || empty($PAGE->course) || (int)$PAGE->course->id === SITEID) {
            return;
        }
        if (!str_starts_with((string)$PAGE->pagetype, 'course-view-')) {
            return;
        }

        $courseid = (int)$PAGE->course->id;
        $categoryid = (int)($PAGE->course->category ?? 0);
        $summary = course_indicator::get_for_course($courseid, $categoryid);
        if (!$summary) {
            return;
        }

        $details = (object)[
            'critical' => $summary->critical,
            'warnings' => $summary->warnings,
            'notices' => $summary->notices,
            'total' => $summary->total,
        ];
        $scaninfo = (object)[
            'profile' => format_string($summary->profilename),
            'date' => userdate($summary->scandate),
        ];
        $reporturl = new \moodle_url('/admin/tool/coursecompliance/course_issues.php', [
            'courseid' => $courseid,
        ]);
        $alertclass = $summary->critical > 0 ? 'alert-danger' : 'alert-warning';
        $buttonclass = $summary->critical > 0 ? 'btn-outline-danger' : 'btn-outline-dark';
        $indicatorid = 'tool-coursecompliance-course-indicator';

        $content = \html_writer::start_div(
            'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2'
        );
        $content .= \html_writer::start_div();
        $content .= \html_writer::tag(
            'strong',
            $hook->renderer->pix_icon('i/warning', '') . ' ' .
                get_string('courseindicator_title', 'tool_coursecompliance')
        );
        $content .= \html_writer::div(
            get_string('courseindicator_summary', 'tool_coursecompliance', $details),
            'mt-1'
        );
        $content .= \html_writer::div(
            get_string('courseindicator_lastscan', 'tool_coursecompliance', $scaninfo),
            'small mt-1'
        );
        $content .= \html_writer::end_div();
        $content .= \html_writer::link(
            $reporturl,
            get_string('courseindicator_review', 'tool_coursecompliance'),
            ['class' => 'btn btn-sm ' . $buttonclass]
        );
        $content .= \html_writer::end_div();

        $indicator = \html_writer::div(
            $content,
            'alert ' . $alertclass . ' shadow-sm mb-3',
            ['role' => 'alert']
        );
        $hook->add_html(\html_writer::div($indicator, 'tool-coursecompliance-course-indicator', [
            'id' => $indicatorid,
        ]));
        $PAGE->requires->js_call_amd('tool_coursecompliance/course_indicator', 'init', [$indicatorid]);
    }
}
