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
 * Session schedule preview/report renderer.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Builds the compact course-by-period report used by Session planning.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_preview_report {
    /** @var string[] Session periods in display order. */
    private const PERIODS = ['exam', 'resit1', 'resit2'];

    /** @var string[] Supported target modules. */
    private const MODULES = ['quiz', 'assign'];

    /**
     * Render the session report.
     *
     * @param \stdClass $batch Batch record.
     * @param array $batchconfig Decoded batch filter configuration.
     * @param int $page Zero-based page number.
     * @param int $perpage Courses per page.
     * @return string HTML.
     */
    public static function render(\stdClass $batch, array $batchconfig, int $page, int $perpage): string {
        global $DB, $OUTPUT;

        $periods = $batchconfig['periods'] ?? [];
        [$courses, $totalcourses] = self::get_courses($batch, $batchconfig, $page, $perpage);
        $courseids = array_keys($courses);
        $issues = self::get_issues((int)$batch->id, $courseids);
        $matches = self::get_matches($courseids, $periods);
        $changes = self::get_changes((int)$batch->id, $courseids);

        $totalissues = $DB->count_records('tool_coursecompliance_session_issue', ['batchid' => $batch->id]);
        $targetcount = self::target_count($periods) * $totalcourses;
        $foundcount = max(0, $targetcount - $totalissues);

        $heading = $batch->status === 'preview'
            ? get_string('sessionpreviewstats', 'tool_coursecompliance')
            : get_string('sessionoperationstats', 'tool_coursecompliance');
        $stats = \html_writer::tag('strong', $heading, ['class' => 'd-block mb-1']);
        $stats .= \html_writer::div(
            get_string('sessiontargetfound', 'tool_coursecompliance', $foundcount),
            'mb-1'
        );
        $issuestext = get_string('sessionskippederrors', 'tool_coursecompliance', $totalissues);
        if ($totalissues > 0) {
            $issuestext = \html_writer::link(
                new \moodle_url('/admin/tool/coursecompliance/session_issues.php', ['batchid' => $batch->id]),
                $issuestext
            );
        }
        $stats .= \html_writer::div($issuestext);
        $html = \html_writer::div($stats, 'alert alert-info mb-4');

        if (!$courses) {
            return $html . $OUTPUT->notification(get_string('nocoursesinscope', 'tool_coursecompliance'), 'info');
        }

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable table-hover align-middle';
        $table->head = [get_string('course')];
        foreach (self::PERIODS as $period) {
            $table->head[] = get_string('period_' . $period, 'tool_coursecompliance');
        }

        foreach ($courses as $course) {
            $row = [\html_writer::link(
                new \moodle_url('/course/view.php', ['id' => $course->id]),
                format_string($course->fullname)
            )];
            foreach (self::PERIODS as $period) {
                $row[] = self::render_period_cell(
                    (int)$course->id,
                    $period,
                    $periods,
                    $issues,
                    $matches,
                    $changes,
                    (string)$batch->status
                );
            }
            $table->data[] = $row;
        }

        $html .= \html_writer::table($table);
        $url = new \moodle_url('/admin/tool/coursecompliance/activity_batch.php', [
            'batchid' => $batch->id,
            'perpage' => $perpage,
        ]);
        $html .= $OUTPUT->paging_bar($totalcourses, $page, $perpage, $url);
        return $html;
    }

    /**
     * Get courses in the batch scope.
     *
     * @param \stdClass $batch Batch record.
     * @param array $batchconfig Batch configuration.
     * @param int $page Page number.
     * @param int $perpage Courses per page.
     * @return array{0:array,1:int}
     */
    private static function get_courses(\stdClass $batch, array $batchconfig, int $page, int $perpage): array {
        global $DB;

        $category = \core_course_category::get((int)$batch->categoryid, MUST_EXIST, true);
        $categoryids = [(int)$batch->categoryid];
        if (!empty($batchconfig['includesubcategories'])) {
            $categoryids = array_merge($categoryids, $category->get_all_children_ids());
        }
        [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'previewcat');
        $params = $catparams + ['siteid' => SITEID];
        $select = "category $catsql AND id <> :siteid";
        $total = $DB->count_records_select('course', $select, $params);
        $courses = $DB->get_records_select(
            'course',
            $select,
            $params,
            'fullname ASC, id ASC',
            'id,fullname,shortname,category',
            $page * $perpage,
            $perpage
        );
        return [$courses, $total];
    }

    /**
     * Count configured target slots for one course.
     *
     * @param array $periods Period configuration.
     * @return int
     */
    private static function target_count(array $periods): int {
        $count = 0;
        foreach (self::PERIODS as $period) {
            if (empty($periods[$period])) {
                continue;
            }
            foreach (self::MODULES as $modname) {
                if (!empty($periods[$period][$modname . 'idnumber'])) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Load issues for courses on the current page.
     *
     * @param int $batchid Batch id.
     * @param int[] $courseids Course ids.
     * @return array
     */
    private static function get_issues(int $batchid, array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$coursesql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'issuecourse');
        $params = array_merge(['batchid' => $batchid], $courseparams);
        $records = $DB->get_records_select(
            'tool_coursecompliance_session_issue',
            "batchid = :batchid AND courseid $coursesql",
            $params,
            'courseid ASC, assessmenttype ASC, modname ASC'
        );
        $out = [];
        foreach ($records as $issue) {
            $out[(int)$issue->courseid][(string)$issue->assessmenttype][(string)$issue->modname] = $issue;
        }
        return $out;
    }

    /**
     * Load target activities for courses on the current page.
     *
     * @param int[] $courseids Course ids.
     * @param array $periods Period configuration.
     * @return array
     */
    private static function get_matches(array $courseids, array $periods): array {
        global $DB;

        if (!$courseids) {
            return [];
        }

        $targets = ['quiz' => [], 'assign' => []];
        foreach ($periods as $period => $config) {
            foreach (self::MODULES as $modname) {
                $idnumber = trim((string)($config[$modname . 'idnumber'] ?? ''));
                if ($idnumber !== '') {
                    $targets[$modname][$idnumber] = $period;
                }
            }
        }

        $out = [];
        foreach (self::MODULES as $modname) {
            if (!$targets[$modname]) {
                continue;
            }
            [$coursesql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, $modname . 'reportcourse');
            [$idsql, $idparams] = $DB->get_in_or_equal(
                array_keys($targets[$modname]),
                SQL_PARAMS_NAMED,
                $modname . 'reportid'
            );
            if ($modname === 'quiz') {
                $fields = 'q.id AS instanceid, q.name, q.timeopen, q.timeclose';
                $alias = 'q';
            } else {
                $fields = 'a.id AS instanceid, a.name, a.allowsubmissionsfromdate, a.duedate, '
                    . 'a.cutoffdate, a.gradingduedate';
                $alias = 'a';
            }
            $sql = "SELECT cm.id AS cmid, cm.course AS courseid, cm.idnumber, {$fields}
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                      JOIN {{$modname}} {$alias} ON {$alias}.id = cm.instance AND {$alias}.course = cm.course
                     WHERE cm.course $coursesql
                       AND cm.idnumber $idsql
                       AND cm.deletioninprogress = 0
                  ORDER BY cm.id ASC";
            $params = array_merge($courseparams, $idparams, ['modulename' => $modname]);
            foreach ($DB->get_records_sql($sql, $params) as $record) {
                $period = $targets[$modname][(string)$record->idnumber] ?? '';
                if ($period !== '') {
                    $out[(int)$record->courseid][$period][$modname][] = $record;
                }
            }
        }
        return $out;
    }

    /**
     * Load stored changes for courses on the current page.
     *
     * @param int $batchid Batch id.
     * @param int[] $courseids Course ids.
     * @return array
     */
    private static function get_changes(int $batchid, array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$coursesql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'changecourse');
        $params = array_merge(['batchid' => $batchid], $courseparams);
        $records = $DB->get_records_select(
            'tool_coursecompliance_act_change',
            "batchid = :batchid AND courseid $coursesql",
            $params,
            'courseid ASC, cmid ASC, id ASC'
        );
        $out = [];
        foreach ($records as $change) {
            $out[(int)$change->cmid][(string)$change->fieldname] = $change;
        }
        return $out;
    }

    /**
     * Render one assessment period cell.
     *
     * @param int $courseid Course id.
     * @param string $period Period key.
     * @param array $periods Period configuration.
     * @param array $issues Issues indexed by course/period/module.
     * @param array $matches Matches indexed by course/period/module.
     * @param array $changes Changes indexed by cmid/field.
     * @param string $batchstatus Batch status.
     * @return string HTML.
     */
    private static function render_period_cell(
        int $courseid,
        string $period,
        array $periods,
        array $issues,
        array $matches,
        array $changes,
        string $batchstatus
    ): string {
        if (empty($periods[$period])) {
            return \html_writer::span('— ' . get_string('notselected', 'tool_coursecompliance') . ' —', 'text-muted');
        }

        $config = $periods[$period];
        $configuredmodules = [];
        foreach (self::MODULES as $modname) {
            if (!empty($config[$modname . 'idnumber'])) {
                $configuredmodules[] = $modname;
            }
        }

        $blocks = [];
        foreach ($configuredmodules as $modname) {
            $labelneeded = count($configuredmodules) > 1;
            $issue = $issues[$courseid][$period][$modname] ?? null;
            if ($issue) {
                $problem = get_string('sessionissue_' . $issue->issuecode, 'tool_coursecompliance', $issue->details);
                $body = \html_writer::span(s($problem), 'text-danger fw-semibold');
                $blocks[] = self::module_block($modname, $body, $labelneeded);
                continue;
            }

            $found = $matches[$courseid][$period][$modname] ?? [];
            if (count($found) !== 1) {
                $problem = count($found) > 1
                    ? get_string('sessionissue_duplicate', 'tool_coursecompliance', count($found))
                    : get_string('sessionissue_missing', 'tool_coursecompliance');
                $body = \html_writer::span(s($problem), 'text-danger fw-semibold');
                $blocks[] = self::module_block($modname, $body, $labelneeded);
                continue;
            }

            $activity = reset($found);
            $body = self::render_activity_change($activity, $modname, $config, $changes, $batchstatus);
            $blocks[] = self::module_block($modname, $body, $labelneeded);
        }

        return implode(\html_writer::empty_tag('hr', ['class' => 'my-2']), $blocks);
    }

    /**
     * Add an optional module label to a period block.
     *
     * @param string $modname Module name.
     * @param string $body Block body.
     * @param bool $showlabel Whether to show module label.
     * @return string HTML.
     */
    private static function module_block(string $modname, string $body, bool $showlabel): string {
        if (!$showlabel) {
            return $body;
        }
        $label = \html_writer::div(get_string('modulename', $modname), 'small text-muted fw-semibold mb-1');
        return $label . $body;
    }

    /**
     * Render old and new primary dates for one matched target.
     *
     * @param \stdClass $activity Activity record.
     * @param string $modname Module name.
     * @param array $config Period configuration.
     * @param array $changes Stored changes by cmid/field.
     * @param string $batchstatus Batch status.
     * @return string HTML.
     */
    private static function render_activity_change(
        \stdClass $activity,
        string $modname,
        array $config,
        array $changes,
        string $batchstatus
    ): string {
        $cmchanges = $changes[(int)$activity->cmid] ?? [];
        if ($modname === 'quiz') {
            $openfield = 'timeopen';
            $closefield = 'timeclose';
        } else {
            $openfield = 'allowsubmissionsfromdate';
            $closefield = 'duedate';
        }

        $currentopen = (int)($activity->{$openfield} ?? 0);
        $currentclose = (int)($activity->{$closefield} ?? 0);
        $oldopen = isset($cmchanges[$openfield]) ? (int)$cmchanges[$openfield]->oldvalue : $currentopen;
        $oldclose = isset($cmchanges[$closefield]) ? (int)$cmchanges[$closefield]->oldvalue : $currentclose;
        $newopen = (int)$config['open'];
        $newclose = (int)$config['close'];

        $oldwindow = self::format_window($oldopen, $oldclose);
        $newwindow = self::format_window($newopen, $newclose);
        $hasprimarychanges = isset($cmchanges[$openfield]) || isset($cmchanges[$closefield]);
        $hasextrachanges = $modname === 'assign'
            && (isset($cmchanges['cutoffdate']) || isset($cmchanges['gradingduedate']));

        if (!$hasprimarychanges && $oldopen === $newopen && $oldclose === $newclose) {
            $html = \html_writer::div(s($newwindow), 'text-success fw-semibold');
            $message = $hasextrachanges
                ? get_string('sessionassignmentsecondarydates', 'tool_coursecompliance')
                : get_string('sessionalreadycorrect', 'tool_coursecompliance');
            $html .= \html_writer::div($message, 'small text-muted');
            return $html;
        }

        $html = \html_writer::div(s($oldwindow), 'text-warning');
        $html .= \html_writer::div('→', 'my-1');
        $html .= \html_writer::div(s($newwindow), 'text-primary fw-semibold');
        if ($hasextrachanges) {
            $secondarymessage = get_string('sessionassignmentsecondarydates', 'tool_coursecompliance');
            $html .= \html_writer::div($secondarymessage, 'small text-muted mt-1');
        }

        $status = self::change_status($cmchanges, $batchstatus);
        if ($status !== '') {
            $html .= \html_writer::div($status, 'small mt-1');
        }
        return $html;
    }

    /**
     * Resolve a compact status label for activity changes.
     *
     * @param array $changes Changes for one activity.
     * @param string $batchstatus Batch status.
     * @return string HTML.
     */
    private static function change_status(array $changes, string $batchstatus): string {
        if (!$changes || $batchstatus === 'preview') {
            return '';
        }
        $statuses = array_values(array_unique(array_map(static fn ($change) => (string)$change->status, $changes)));
        if (in_array('failed', $statuses, true)) {
            return \html_writer::span(get_string('changestatus_failed', 'tool_coursecompliance'), 'text-danger fw-semibold');
        }
        if (in_array('skipped', $statuses, true)) {
            return \html_writer::span(get_string('changestatus_skipped', 'tool_coursecompliance'), 'text-warning fw-semibold');
        }
        if ($statuses && count(array_diff($statuses, ['applied'])) === 0) {
            return \html_writer::span(get_string('changestatus_applied', 'tool_coursecompliance'), 'text-success fw-semibold');
        }
        return \html_writer::span(get_string('changestatus_pending', 'tool_coursecompliance'), 'text-muted');
    }

    /**
     * Format an opening/closing window.
     *
     * @param int $open Opening timestamp.
     * @param int $close Closing timestamp.
     * @return string Plain display text.
     */
    private static function format_window(int $open, int $close): string {
        $openlabel = $open ? userdate($open) : '—';
        $closelabel = $close ? userdate($close) : '—';
        return $openlabel . ' — ' . $closelabel;
    }
}
