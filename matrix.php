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
 * Course readiness matrix.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:view', $context);

$scanid = optional_param('scanid', 0, PARAM_INT);
if (!$scanid) {
    $records = $DB->get_records_select(
        'tool_coursecompliance_scan',
        "status = :status AND courselimit = 0 AND scantype = :scantype",
        ['status' => 'complete', 'scantype' => 'audit'],
        'id DESC',
        '*',
        0,
        1
    );
    if (!$records) {
        $records = $DB->get_records(
            'tool_coursecompliance_scan',
            ['status' => 'complete', 'scantype' => 'audit'],
            'id DESC',
            '*',
            0,
            1
        );
    }
    $scanid = $records ? (int)reset($records)->id : 0;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/matrix.php', $scanid ? ['scanid' => $scanid] : []));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('readinessmatrix', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('readinessmatrix', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('audit');
echo \tool_coursecompliance\ui::audit_navigation('matrix');
echo \tool_coursecompliance\ui::page_heading(
    get_string('readinessmatrix', 'tool_coursecompliance'),
    get_string('readinessmatrixintro', 'tool_coursecompliance')
);

if (!$scanid) {
    echo $OUTPUT->notification(get_string('nocompletedscans', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid, 'status' => 'complete'], '*', MUST_EXIST);
$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid]);
$category = \core_course_category::get($scan->categoryid, MUST_EXIST, true);
$rows = \tool_coursecompliance\scan_analysis::readiness_matrix($scanid);
$delta = \tool_coursecompliance\scan_analysis::delta($scanid);

$ready = 0;
$attention = 0;
foreach ($rows as $row) {
    if (($row['state']->status ?? '') === 'ready') {
        $ready++;
    } else {
        $attention++;
    }
}
$percent = count($rows) ? (int)round(($ready / count($rows)) * 100) : 100;
echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('coursereadinesspercent', 'tool_coursecompliance'),
        'value' => $percent . '%',
        'tone' => $percent === 100 ? 'success' : ($percent >= 80 ? 'warning' : 'danger'),
    ],
    [
        'label' => get_string('readycourses', 'tool_coursecompliance'),
        'value' => $ready,
        'tone' => 'success',
    ],
    [
        'label' => get_string('notreadycourses', 'tool_coursecompliance'),
        'value' => $attention,
        'tone' => $attention ? 'danger' : 'success',
    ],
    [
        'label' => get_string('becameready', 'tool_coursecompliance'),
        'value' => (int)$delta['becameready'],
        'tone' => 'success',
    ],
    [
        'label' => get_string('lostreadiness', 'tool_coursecompliance'),
        'value' => (int)$delta['lostready'],
        'tone' => !empty($delta['lostready']) ? 'danger' : 'success',
    ],
]);

$contextline = format_string($category->name);
if ($profile) {
    $contextline .= ' · ' . format_string($profile->name);
}
$contextline .= ' · ' . userdate((int)$scan->timecreated);
echo html_writer::div($contextline, 'text-muted mb-3');

if (!$rows) {
    echo $OUTPUT->notification(get_string('nomatrixdata', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$headers = [
    'course' => get_string('course'),
    'overall' => get_string('overallstatus', 'tool_coursecompliance'),
    'exam' => get_string('period_exam', 'tool_coursecompliance'),
    'resit1' => get_string('period_resit1', 'tool_coursecompliance'),
    'resit2' => get_string('period_resit2', 'tool_coursecompliance'),
    'questionbank' => get_string('matrix_questionbank', 'tool_coursecompliance'),
    'availability' => get_string('matrix_availability', 'tool_coursecompliance'),
    'gradebook' => get_string('matrix_gradebook', 'tool_coursecompliance'),
    'completion' => get_string('matrix_completion', 'tool_coursecompliance'),
];
$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle cc-readiness-matrix';
$table->head = array_values($headers);
foreach ($rows as $courseid => $row) {
    $course = $row['course'];
    $state = $row['state'];
    $data = [];
    $data[] = html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        format_string($course->fullname)
    ) . html_writer::div(s($course->shortname), 'small text-muted');
    $data[] = \tool_coursecompliance\ui::readiness_badge((string)($state->status ?? 'ready')) .
        html_writer::div((int)($state->score ?? 100) . '%', 'small text-muted mt-1');
    foreach (['exam', 'resit1', 'resit2', 'questionbank', 'availability', 'gradebook', 'completion'] as $dimension) {
        $status = (string)($row['cells'][$dimension] ?? 'ready');
        $url = new moodle_url('/admin/tool/coursecompliance/report.php', [
            'scanid' => $scanid,
            'courseid' => $courseid,
        ]);
        $data[] = html_writer::link(
            $url,
            \tool_coursecompliance\ui::readiness_badge($status),
            ['class' => 'text-decoration-none']
        );
    }
    $table->data[] = $data;
}
$body = html_writer::div(html_writer::table($table), 'table-responsive');
echo \tool_coursecompliance\ui::panel(
    get_string('readinessmatrix', 'tool_coursecompliance'),
    $body,
    get_string('matrixlegend', 'tool_coursecompliance')
);

echo $OUTPUT->footer();
