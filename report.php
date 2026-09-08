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
 * Compliance scan report page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_capability('tool/coursecompliance:view', context_system::instance());

$scanid = required_param('scanid', PARAM_INT);
$severity = optional_param('severity', '', PARAM_ALPHA);
$assessmenttype = optional_param('assessmenttype', '', PARAM_ALPHANUMEXT);
$scan = $DB->get_record('tool_cc_scan', ['id' => $scanid], '*', MUST_EXIST);
$profile = $DB->get_record('tool_cc_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
$category = \core_course_category::get($scan->categoryid, MUST_EXIST, true);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('report', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('report', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($category->name) . ' — ' . format_string($profile->name));

if ($scan->status === 'queued' || $scan->status === 'running') {
    echo html_writer::div(get_string('scanrunning', 'tool_coursecompliance'), 'alert alert-info');
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]),
        get_string('refresh')
    );
    echo $OUTPUT->footer();
    exit;
}
if ($scan->status === 'failed') {
    echo html_writer::div(get_string('scanfailed', 'tool_coursecompliance') . ': ' . s($scan->errormessage), 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

$summary = new html_table();
$summary->attributes['class'] = 'generaltable';
$summary->data = [
    [get_string('courses', 'tool_coursecompliance'), $scan->courses],
    [get_string('assessments', 'tool_coursecompliance'), $scan->assessments],
    [get_string('critical', 'tool_coursecompliance'), $scan->critical],
    [get_string('warnings', 'tool_coursecompliance'), $scan->warnings],
    [get_string('notices', 'tool_coursecompliance'), $scan->notices],
    [get_string('ignored', 'tool_coursecompliance'), $scan->ignored ?? 0],
];
echo html_writer::table($summary);
echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/readiness.php', ['scanid' => $scanid]),
        get_string('sessionreadiness', 'tool_coursecompliance'),
        ['class' => 'btn btn-primary me-2']
    ) . ' ' . html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/assessment.php', ['scanid' => $scanid]),
        get_string('assessmentauditor', 'tool_coursecompliance'),
        ['class' => 'btn btn-secondary']
    ),
    'mb-3'
);

$baseurl = new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]);
echo html_writer::div(
    html_writer::link($baseurl, get_string('all', 'tool_coursecompliance'), ['class' => 'btn btn-secondary me-2']) . ' ' .
    html_writer::link(
        new moodle_url($baseurl, ['severity' => 'critical']),
        get_string('critical', 'tool_coursecompliance'),
        ['class' => 'btn btn-danger me-2']
    ) . ' ' .
    html_writer::link(
        new moodle_url($baseurl, ['severity' => 'warning']),
        get_string('warnings', 'tool_coursecompliance'),
        ['class' => 'btn btn-warning']
    ),
    'mb-3'
);

$params = ['scanid' => $scanid];
$where = 'r.scanid = :scanid';
if (in_array($severity, ['critical', 'warning', 'notice'], true)) {
    $where .= ' AND r.severity = :severity';
    $params['severity'] = $severity;
}
if (in_array($assessmenttype, ['exam', 'resit1', 'resit2'], true)) {
    $where .= ' AND r.assessmenttype = :assessmenttype';
    $params['assessmenttype'] = $assessmenttype;
}
$sql = "SELECT r.*, c.fullname, c.shortname
          FROM {tool_cc_result} r
          JOIN {course} c ON c.id = r.courseid
         WHERE $where
      ORDER BY CASE r.severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END, c.fullname, r.rulecode";
$results = $DB->get_records_sql($sql, $params, 0, 5000);

$table = new html_table();
$table->head = [
    get_string('severity', 'tool_coursecompliance'),
    get_string('course'),
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('component', 'tool_coursecompliance'),
    get_string('rule', 'tool_coursecompliance'),
    get_string('problem', 'tool_coursecompliance'),
    get_string('expected', 'tool_coursecompliance'),
    get_string('actual', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($results as $result) {
    $courseurl = new moodle_url('/course/view.php', ['id' => $result->courseid]);
    $actions = '';
    if (has_capability('tool/coursecompliance:manageprofiles', context_system::instance())) {
        $actions = html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/exception_add.php', ['resultid' => $result->id]),
            get_string('createexception', 'tool_coursecompliance')
        );
    }
    $period = $result->assessmenttype !== ''
        ? get_string('period_' . $result->assessmenttype, 'tool_coursecompliance')
        : '—';
    $table->data[] = [
        get_string('severity_' . $result->severity, 'tool_coursecompliance'),
        html_writer::link($courseurl, format_string($result->fullname)),
        $period,
        s($result->component),
        s($result->rulecode),
        s($result->message),
        s($result->expected),
        s($result->actual),
        $actions,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
