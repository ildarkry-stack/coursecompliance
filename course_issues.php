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
 * Current CourseCompliance problems for one course.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);
if (!is_siteadmin()) {
    throw new required_capability_exception(
        context_system::instance(),
        'moodle/site:config',
        'nopermissions',
        ''
    );
}

$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);
$summary = \tool_coursecompliance\course_indicator::get_for_course($courseid, (int)$course->category);

$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/course_issues.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('courseissues_title', 'tool_coursecompliance'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseissues_title', 'tool_coursecompliance'));
echo html_writer::div(
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('backtocourse', 'tool_coursecompliance'),
        ['class' => 'btn btn-outline-secondary me-2']
    ) .
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/course_history.php', ['courseid' => $courseid]),
        get_string('coursehistory', 'tool_coursecompliance'),
        ['class' => 'btn btn-outline-primary']
    ),
    'mb-3'
);

if (!$summary) {
    echo $OUTPUT->notification(get_string('courseissues_notchecked', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if ($summary->ready) {
    echo $OUTPUT->notification(get_string('courseissues_clean', 'tool_coursecompliance'), 'success');
    echo html_writer::div(
        get_string('courseindicator_lastscan', 'tool_coursecompliance', (object)[
            'profile' => format_string($summary->profilename),
            'date' => userdate($summary->scandate),
        ]),
        'text-muted small'
    );
    echo $OUTPUT->footer();
    exit;
}

$details = (object)[
    'critical' => $summary->critical,
    'warnings' => $summary->warnings,
    'notices' => $summary->notices,
    'total' => $summary->total,
];
echo html_writer::div(
    get_string('courseindicator_summary', 'tool_coursecompliance', $details),
    $summary->critical > 0 ? 'alert alert-danger' : 'alert alert-warning'
);

[$insql, $params] = $DB->get_in_or_equal($summary->scanids, SQL_PARAMS_NAMED, 'courseissuescan');
$params['courseid'] = $courseid;
$sql = "SELECT r.*, p.name AS profilename
          FROM {tool_coursecompliance_result} r
          JOIN {tool_coursecompliance_scan} s ON s.id = r.scanid
          JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
         WHERE r.scanid $insql AND r.courseid = :courseid
      ORDER BY CASE r.severity
                   WHEN 'critical' THEN 1
                   WHEN 'warning' THEN 2
                   ELSE 3
               END,
               r.assessmenttype,
               r.rulecode";
$results = $DB->get_records_sql($sql, $params);
$questiondetailcounts = \tool_coursecompliance\question_issue_repository::counts_for_results(array_values($results));

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    get_string('profile', 'tool_coursecompliance'),
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('severity', 'tool_coursecompliance'),
    get_string('problem', 'tool_coursecompliance'),
    get_string('assessment', 'tool_coursecompliance'),
    get_string('expected', 'tool_coursecompliance'),
    get_string('actual', 'tool_coursecompliance'),
    get_string('actions'),
];
$stringmanager = get_string_manager();
foreach ($results as $result) {
    $problem = $stringmanager->string_exists($result->rulecode, 'tool_coursecompliance')
        ? get_string($result->rulecode, 'tool_coursecompliance')
        : (string)$result->message;
    $period = in_array($result->assessmenttype, ['exam', 'resit1', 'resit2'], true)
        ? get_string('period_' . $result->assessmenttype, 'tool_coursecompliance')
        : get_string('course');
    $actions = '';
    $questiondetailcount = $questiondetailcounts[(int)$result->id] ?? 0;
    if ($questiondetailcount > 0) {
        $actions = html_writer::link(
            \tool_coursecompliance\question_issue_repository::url_for_result($result),
            get_string('showproblemquestions', 'tool_coursecompliance', $questiondetailcount),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }
    $table->data[] = [
        format_string($result->profilename),
        $period,
        \tool_coursecompliance\ui::severity_badge((string)$result->severity),
        s($problem),
        $result->objectname ? format_string($result->objectname) : '—',
        $result->expected !== null && $result->expected !== '' ? s($result->expected) : '—',
        $result->actual !== null && $result->actual !== '' ? s($result->actual) : '—',
        $actions,
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
