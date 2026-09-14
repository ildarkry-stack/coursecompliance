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
    /** @var bool Whether the course UI AMD module has already been registered. */
    private static bool $courseuiregistered = false;

    /**
     * Register the administrator-only course status UI as a fallback.
     *
     * The course navigation callback is the primary registration path because
     * it is already known to run on the target installation. This hook remains
     * as a harmless fallback for themes/layouts which do not build course
     * navigation in the usual way.
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

        $summary = course_indicator::get_for_course(
            (int)$PAGE->course->id,
            (int)($PAGE->course->category ?? 0)
        );
        if ($summary) {
            self::register_course_ui((int)$PAGE->course->id, $summary);
        }
    }

    /**
     * Register the coloured course-status banners on the course home page.
     *
     * @param int $courseid Course id.
     * @param \stdClass $summary Current course audit summary.
     */
    public static function register_course_ui(int $courseid, \stdClass $summary): void {
        global $PAGE;

        if (self::$courseuiregistered || !is_siteadmin() || $courseid <= 0) {
            return;
        }
        if (!str_starts_with((string)$PAGE->pagetype, 'course-view-')) {
            return;
        }
        self::$courseuiregistered = true;

        $reporturl = new \moodle_url('/admin/tool/coursecompliance/course_issues.php', [
            'courseid' => $courseid,
        ]);
        $scaninfo = (object)[
            'profile' => format_string($summary->profilename),
            'date' => userdate($summary->scandate),
        ];
        $payload = [
            'courseid' => $courseid,
            'critical' => (int)$summary->critical,
            'warnings' => (int)$summary->warnings,
            'notices' => (int)$summary->notices,
            'total' => (int)$summary->total,
            'ready' => (bool)$summary->ready,
            'criticaltitle' => get_string(
                'courseindicator_critical_title',
                'tool_coursecompliance',
                $summary->critical
            ),
            'criticaltext' => get_string('courseindicator_critical_text', 'tool_coursecompliance'),
            'warningtitle' => get_string(
                'courseindicator_warning_title',
                'tool_coursecompliance',
                $summary->warnings
            ),
            'warningtext' => get_string('courseindicator_warning_text', 'tool_coursecompliance'),
            'noticetitle' => get_string(
                'courseindicator_notice_title',
                'tool_coursecompliance',
                $summary->notices
            ),
            'noticetext' => get_string('courseindicator_notice_text', 'tool_coursecompliance'),
            'readytitle' => get_string('courseindicator_ready_title', 'tool_coursecompliance'),
            'readytext' => get_string('courseindicator_ready_text', 'tool_coursecompliance'),
            'lastscan' => get_string('courseindicator_lastscan', 'tool_coursecompliance', $scaninfo),
            'reviewlabel' => get_string(
                $summary->ready ? 'courseindicator_review_ready' : 'courseindicator_review',
                'tool_coursecompliance'
            ),
            'reporturl' => $reporturl->out(false),
        ];

        $PAGE->requires->js_call_amd('tool_coursecompliance/course_indicator', 'init', [$payload]);
    }
}
