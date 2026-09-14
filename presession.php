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
 * Pre-session readiness view.
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
$statusfilter = optional_param('status', '', PARAM_ALPHA);
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
    $scanid = $records ? (int)reset($records)->id : 0;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/presession.php', $scanid ? ['scanid' => $scanid] : []));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('presessioncheck', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('presessioncheck', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('audit');
echo \tool_coursecompliance\ui::audit_navigation('presession');
echo \tool_coursecompliance\ui::page_heading(
    get_string('presessioncheck', 'tool_coursecompliance'),
    get_string('presessionintro', 'tool_coursecompliance'),
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/index.php'),
        get_string('startnewscan', 'tool_coursecompliance'),
        ['class' => 'btn btn-primary']
    )
);

if (!$scanid) {
    echo $OUTPUT->notification(get_string('nocompletedscans', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid, 'status' => 'complete'], '*', MUST_EXIST);
$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
$config = \tool_coursecompliance\profile_config::decode($profile->configjson);
$states = \tool_coursecompliance\scan_analysis::composite_course_snapshots($scanid);
$courseids = array_keys($states);
$courses = $courseids
    ? $DB->get_records_list('course', 'id', $courseids, 'fullname ASC', 'id,fullname,shortname')
    : [];
$assessments = \tool_coursecompliance\scan_analysis::composite_assessments($scanid);
$assessmentmap = [];
foreach ($assessments as $assessment) {
    $assessmentmap[(int)$assessment->courseid][(string)$assessment->assessmenttype] = $assessment;
}

$requiredtypes = [];
$useblueprintrules = (string)($config['blueprintmode'] ?? 'manual') !== 'manual'
    && !empty($config['blueprintrules']['enabled']);
foreach (['exam', 'resit1', 'resit2'] as $type) {
    $key = $type === 'exam' ? 'requireexam' : 'require' . $type;
    $required = $useblueprintrules
        ? !empty($config['blueprintrules']['assessments'][$type]['required'])
        : !empty($config[$key]);
    if ($required) {
        $requiredtypes[] = $type;
    }
}

$ready = [];
$notready = [];
foreach ($courses as $course) {
    $state = $states[(int)$course->id];
    $blockers = [];
    if ((string)$state->status !== 'ready') {
        $blockers[] = get_string(
            'coursecomplianceissuescount',
            'tool_coursecompliance',
            (int)$state->critical + (int)$state->warnings + (int)$state->notices
        );
    }
    foreach ($requiredtypes as $type) {
        $assessment = $assessmentmap[(int)$course->id][$type] ?? null;
        if (!$assessment || empty($assessment->existsflag)) {
            $blockers[] = get_string(
                'missingassessmenttype',
                'tool_coursecompliance',
                get_string('period_' . $type, 'tool_coursecompliance')
            );
            continue;
        }
        if ((int)$assessment->critical > 0 || (int)$assessment->warnings > 0) {
            $blockers[] = get_string(
                'assessmenthasissues',
                'tool_coursecompliance',
                get_string('period_' . $type, 'tool_coursecompliance')
            );
        }
    }
    $entry = ['course' => $course, 'state' => $state, 'blockers' => array_values(array_unique($blockers))];
    if (!$blockers) {
        $ready[] = $entry;
    } else {
        $notready[] = $entry;
    }
}
$total = count($ready) + count($notready);
$percent = $total ? (int)round((count($ready) / $total) * 100) : 100;
$delta = \tool_coursecompliance\scan_analysis::delta($scanid);

echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('sessionreadycourses', 'tool_coursecompliance'),
        'value' => count($ready),
        'tone' => 'success',
    ],
    [
        'label' => get_string('sessionnotreadycourses', 'tool_coursecompliance'),
        'value' => count($notready),
        'tone' => $notready ? 'danger' : 'success',
    ],
    [
        'label' => get_string('sessionreadinesspercent', 'tool_coursecompliance'),
        'value' => $percent . '%',
        'tone' => $percent === 100 ? 'success' : ($percent >= 80 ? 'warning' : 'danger'),
    ],
    [
        'label' => get_string('newissues', 'tool_coursecompliance'),
        'value' => (int)$delta['newcount'],
        'tone' => !empty($delta['newcount']) ? 'danger' : 'success',
    ],
    [
        'label' => get_string('resolvedissues', 'tool_coursecompliance'),
        'value' => (int)$delta['resolvedcount'],
        'tone' => 'success',
    ],
]);

$baseurl = new moodle_url('/admin/tool/coursecompliance/presession.php', ['scanid' => $scanid]);
$filters = [
    html_writer::link(
        $baseurl,
        get_string('all', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($statusfilter === '' ? 'btn-primary' : 'btn-outline-secondary')]
    ),
    html_writer::link(
        new moodle_url($baseurl, ['status' => 'ready']),
        get_string('ready', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($statusfilter === 'ready' ? 'btn-success' : 'btn-outline-success')]
    ),
    html_writer::link(
        new moodle_url($baseurl, ['status' => 'notready']),
        get_string('notready', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($statusfilter === 'notready' ? 'btn-danger' : 'btn-outline-danger')]
    ),
];
echo html_writer::div(implode(' ', $filters), 'd-flex gap-2 mb-3');

$entries = $statusfilter === 'ready' ? $ready : ($statusfilter === 'notready' ? $notready : array_merge($notready, $ready));
$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle';
$table->head = [
    get_string('course'),
    get_string('status'),
    get_string('readinessscore', 'tool_coursecompliance'),
    get_string('sessionblockers', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($entries as $entry) {
    $course = $entry['course'];
    $state = $entry['state'];
    $isready = !$entry['blockers'];
    $table->data[] = [
        html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), format_string($course->fullname)) .
            html_writer::div(s($course->shortname), 'small text-muted'),
        $isready
            ? html_writer::span(get_string('ready', 'tool_coursecompliance'), 'badge bg-success')
            : html_writer::span(get_string('notready', 'tool_coursecompliance'), 'badge bg-danger'),
        (int)$state->score . '%',
        $isready ? '—' : implode('<br>', array_map('s', $entry['blockers'])),
        html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/course_issues.php', ['courseid' => $course->id]),
            get_string('viewproblems', 'tool_coursecompliance'),
            ['class' => 'btn btn-sm btn-outline-primary']
        ),
    ];
}
echo \tool_coursecompliance\ui::panel(
    get_string('presessioncourses', 'tool_coursecompliance'),
    html_writer::div(html_writer::table($table), 'table-responsive'),
    get_string('presessionresulthelp', 'tool_coursecompliance')
);

echo $OUTPUT->footer();
