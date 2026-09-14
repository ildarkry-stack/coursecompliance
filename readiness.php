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
 * Session readiness and assessment audit page.
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
$type = optional_param('assessmenttype', '', PARAM_ALPHANUMEXT);
if (!$scanid) {
    $scanid = (int)$DB->get_field_sql(
        "SELECT MAX(id)
           FROM {tool_coursecompliance_scan}
          WHERE status = 'complete' AND scantype = 'audit' AND courselimit = 0"
    );
    if (!$scanid) {
        $scanid = (int)$DB->get_field_sql(
            "SELECT MAX(id) FROM {tool_coursecompliance_scan} WHERE status = 'complete' AND scantype = 'audit'"
        );
    }
}

$urlparams = ['scanid' => $scanid];
if ($type !== '') {
    $urlparams['assessmenttype'] = $type;
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/readiness.php', $urlparams));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('nav_audit', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('nav_audit', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('audit');
echo \tool_coursecompliance\ui::audit_navigation('readiness');
echo $OUTPUT->heading(get_string('nav_audit', 'tool_coursecompliance'));
echo \tool_coursecompliance\ui::intro(get_string('auditintro', 'tool_coursecompliance'));

if (!$scanid) {
    echo $OUTPUT->notification(get_string('nocompletedscans', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid, 'status' => 'complete'], '*', MUST_EXIST);
$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
$category = \core_course_category::get($scan->categoryid, MUST_EXIST, true);
$delta = \tool_coursecompliance\scan_analysis::delta($scanid);
$states = array_values(\tool_coursecompliance\scan_analysis::composite_assessments($scanid));

$periods = [];
$scoretotal = 0;
$scorecount = 0;
foreach (['exam', 'resit1', 'resit2'] as $periodtype) {
    $periods[$periodtype] = [
        'total' => 0,
        'ready' => 0,
        'warning' => 0,
        'critical' => 0,
        'missing' => 0,
        'score' => 0,
    ];
}
foreach ($states as $state) {
    if (!isset($periods[$state->assessmenttype])) {
        continue;
    }
    $period = &$periods[$state->assessmenttype];
    $period['total']++;
    $period['score'] += (int)$state->score;
    $scoretotal += (int)$state->score;
    $scorecount++;
    if (empty($state->existsflag) && empty($state->ignored)) {
        $period['missing']++;
    } else if ((int)$state->critical > 0) {
        $period['critical']++;
    } else if ((int)$state->warnings > 0) {
        $period['warning']++;
    } else {
        $period['ready']++;
    }
    unset($period);
}
$overallscore = $scorecount ? (int)round($scoretotal / $scorecount) : 100;
$totalready = array_sum(array_column($periods, 'ready'));
$totalmissing = array_sum(array_column($periods, 'missing'));

echo html_writer::div(
    get_string('auditcontext', 'tool_coursecompliance', (object)[
        'category' => format_string($category->name),
        'profile' => format_string($profile->name),
        'date' => userdate((int)$scan->timecreated),
    ]),
    'small text-muted mb-3'
);

echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('readinessscore', 'tool_coursecompliance'),
        'value' => $overallscore . '%',
        'class' => $overallscore >= 90 ? 'text-success' : ($overallscore >= 75 ? 'text-warning' : 'text-danger'),
    ],
    [
        'label' => get_string('courses', 'tool_coursecompliance'),
        'value' => (int)$scan->courses,
    ],
    [
        'label' => get_string('readyassessments', 'tool_coursecompliance'),
        'value' => $totalready,
    ],
    [
        'label' => get_string('missingassessments', 'tool_coursecompliance'),
        'value' => $totalmissing,
        'class' => $totalmissing ? 'text-danger' : 'text-success',
    ],
]);
echo \tool_coursecompliance\ui::metric_cards([
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

$readinesschart = \tool_coursecompliance\report_charts::readiness($periods);
if ($readinesschart) {
    echo html_writer::start_div('card shadow-sm mb-4');
    echo html_writer::start_div('card-body');
    echo $OUTPUT->render($readinesschart);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('total', 'tool_coursecompliance'),
    get_string('ready', 'tool_coursecompliance'),
    get_string('warnings', 'tool_coursecompliance'),
    get_string('critical', 'tool_coursecompliance'),
    get_string('missing', 'tool_coursecompliance'),
    get_string('readinessscore', 'tool_coursecompliance'),
];
foreach ($periods as $periodtype => $period) {
    if (!$period['total']) {
        continue;
    }
    $score = (int)round($period['score'] / $period['total']);
    $table->data[] = [
        get_string('period_' . $periodtype, 'tool_coursecompliance'),
        $period['total'],
        $period['ready'],
        $period['warning'],
        $period['critical'],
        $period['missing'],
        $score . '%',
    ];
}
echo html_writer::table($table);

$problemcounts = [];
foreach (\tool_coursecompliance\scan_analysis::composite_results($scanid) as $result) {
    if ((string)$result->component !== 'quiz') {
        continue;
    }
    $key = (string)$result->rulecode . '|' . (string)$result->severity;
    if (!isset($problemcounts[$key])) {
        $problemcounts[$key] = (object)[
            'rulecode' => (string)$result->rulecode,
            'severity' => (string)$result->severity,
            'issuecount' => 0,
        ];
    }
    $problemcounts[$key]->issuecount++;
}
$problems = array_values($problemcounts);
usort($problems, static function ($left, $right): int {
    return (int)$right->issuecount <=> (int)$left->issuecount;
});
$problems = array_slice($problems, 0, 10);
if ($problems) {
    echo $OUTPUT->heading(get_string('topassessmentproblems', 'tool_coursecompliance'), 3);
    $problemtable = new html_table();
    $problemtable->attributes['class'] = 'generaltable table-sm';
    $problemtable->head = [
        get_string('severity', 'tool_coursecompliance'),
        get_string('problem', 'tool_coursecompliance'),
        get_string('count'),
    ];
    $stringmanager = get_string_manager();
    foreach ($problems as $problem) {
        $label = $stringmanager->string_exists($problem->rulecode, 'tool_coursecompliance')
            ? get_string($problem->rulecode, 'tool_coursecompliance')
            : $problem->rulecode;
        $problemtable->data[] = [
            \tool_coursecompliance\ui::severity_badge((string)$problem->severity),
            s($label),
            (int)$problem->issuecount,
        ];
    }
    echo html_writer::table($problemtable);
}

echo $OUTPUT->heading(get_string('assessmentdetails', 'tool_coursecompliance'), 3);
$baseurl = new moodle_url('/admin/tool/coursecompliance/readiness.php', ['scanid' => $scanid]);
$filterlinks = [
    html_writer::link(
        $baseurl,
        get_string('all', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($type === '' ? 'btn-primary' : 'btn-outline-secondary')]
    ),
];
foreach (['exam', 'resit1', 'resit2'] as $periodtype) {
    $filterlinks[] = html_writer::link(
        new moodle_url($baseurl, ['assessmenttype' => $periodtype]),
        get_string('period_' . $periodtype, 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($type === $periodtype ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $filterlinks), 'mb-3 d-flex flex-wrap gap-2');

$detailstates = [];
$detailcourseids = [];
foreach ($states as $state) {
    if (in_array($type, ['exam', 'resit1', 'resit2'], true) && (string)$state->assessmenttype !== $type) {
        continue;
    }
    $detailstates[] = $state;
    $detailcourseids[(int)$state->courseid] = true;
}
$detailcourses = $detailcourseids
    ? $DB->get_records_list('course', 'id', array_keys($detailcourseids), '', 'id,fullname,shortname')
    : [];
usort($detailstates, static function ($left, $right) use ($detailcourses): int {
    $critical = (int)$right->critical <=> (int)$left->critical;
    if ($critical !== 0) {
        return $critical;
    }
    $warnings = (int)$right->warnings <=> (int)$left->warnings;
    if ($warnings !== 0) {
        return $warnings;
    }
    $score = (int)$left->score <=> (int)$right->score;
    if ($score !== 0) {
        return $score;
    }
    $leftcourse = $detailcourses[(int)$left->courseid] ?? null;
    $rightcourse = $detailcourses[(int)$right->courseid] ?? null;
    return strcasecmp((string)($leftcourse->fullname ?? ''), (string)($rightcourse->fullname ?? ''));
});
$detailstates = array_slice($detailstates, 0, 5000);
if (!$detailstates) {
    echo $OUTPUT->notification(get_string('noassessmentstates', 'tool_coursecompliance'), 'info');
} else {
    $detailtable = new html_table();
    $detailtable->attributes['class'] = 'generaltable table-sm';
    $detailtable->head = [
        get_string('course'),
        get_string('assessmentperiod', 'tool_coursecompliance'),
        get_string('assessment', 'tool_coursecompliance'),
        get_string('readinessscore', 'tool_coursecompliance'),
        get_string('status', 'tool_coursecompliance'),
        get_string('critical', 'tool_coursecompliance'),
        get_string('warnings', 'tool_coursecompliance'),
        get_string('actions'),
    ];
    foreach ($detailstates as $state) {
        $course = $detailcourses[(int)$state->courseid] ?? null;
        if (!$course) {
            continue;
        }
        if (empty($state->existsflag) && empty($state->ignored)) {
            $status = html_writer::span(get_string('missing', 'tool_coursecompliance'), 'badge bg-danger');
        } else if ((int)$state->critical > 0) {
            $status = \tool_coursecompliance\ui::severity_badge('critical');
        } else if ((int)$state->warnings > 0) {
            $status = \tool_coursecompliance\ui::severity_badge('warning');
        } else {
            $status = html_writer::span(get_string('ready', 'tool_coursecompliance'), 'badge bg-success');
        }
        $objectname = empty($state->existsflag)
            ? html_writer::span(get_string('missing', 'tool_coursecompliance'), 'text-danger fw-semibold')
            : format_string($state->objectname);
        $detailtable->data[] = [
            html_writer::link(
                new moodle_url('/course/view.php', ['id' => $state->courseid]),
                format_string($course->fullname)
            ) . html_writer::div(s($course->shortname), 'small text-muted'),
            get_string('period_' . $state->assessmenttype, 'tool_coursecompliance'),
            $objectname,
            (int)$state->score . '%',
            $status,
            (int)$state->critical,
            (int)$state->warnings,
            html_writer::link(
                new moodle_url('/admin/tool/coursecompliance/report.php', [
                    'scanid' => (int)$state->scanid,
                    'courseid' => $state->courseid,
                    'assessmenttype' => $state->assessmenttype,
                ]),
                get_string('viewproblems', 'tool_coursecompliance')
            ),
        ];
    }
    echo html_writer::table($detailtable);
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]),
        get_string('opendetailedreport', 'tool_coursecompliance'),
        ['class' => 'btn btn-primary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
