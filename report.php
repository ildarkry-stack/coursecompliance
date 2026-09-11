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
$context = context_system::instance();
require_capability('tool/coursecompliance:view', $context);

$scanid = required_param('scanid', PARAM_INT);
$severity = optional_param('severity', '', PARAM_ALPHA);
$assessmenttype = optional_param('assessmenttype', '', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$kind = optional_param('kind', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 100;

$scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid]);
$category = \core_course_category::get($scan->categoryid, MUST_EXIST, true);
$config = $profile ? \tool_coursecompliance\profile_config::decode($profile->configjson) : [];

$urlparams = ['scanid' => $scanid];
if ($severity !== '') {
    $urlparams['severity'] = $severity;
}
if ($assessmenttype !== '') {
    $urlparams['assessmenttype'] = $assessmenttype;
}
if ($courseid > 0) {
    $urlparams['courseid'] = $courseid;
}
if ($kind !== '') {
    $urlparams['kind'] = $kind;
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/report.php', $urlparams));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('detailedreport', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('detailedreport', 'tool_coursecompliance'));


echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('audit');
$profilename = $profile ? format_string($profile->name) : get_string('missingprofile', 'tool_coursecompliance');
echo $OUTPUT->heading(get_string('detailedreport', 'tool_coursecompliance'));
echo html_writer::div(
    format_string($category->name) . ' · ' . $profilename . ' · ' . userdate((int)$scan->timecreated),
    'text-muted mb-3'
);

if (!empty($config['blueprint']) && is_array($config['blueprint'])) {
    $source = (object)[
        'course' => $config['blueprint']['sourcefullname'] ?? '',
        'version' => (int)($config['blueprintversion'] ?? 1),
        'date' => !empty($config['blueprint']['capturedat'])
            ? userdate((int)$config['blueprint']['capturedat'])
            : '—',
    ];
    echo html_writer::div(get_string('blueprintreportsource', 'tool_coursecompliance', $source), 'small text-muted mb-3');
}

if ($scan->status === 'queued' || $scan->status === 'running') {
    echo html_writer::div(
        \tool_coursecompliance\ui::status_badge((string)$scan->status) . ' ' .
            s(get_string('scanrunning', 'tool_coursecompliance')),
        'alert alert-info'
    );
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]),
        get_string('refresh')
    );
    echo $OUTPUT->footer();
    exit;
}
if ($scan->status === 'failed') {
    echo html_writer::div(
        \tool_coursecompliance\ui::status_badge('failed') . ' ' .
            s(get_string('scanfailed', 'tool_coursecompliance') . ': ' . $scan->errormessage),
        'alert alert-danger'
    );
    echo $OUTPUT->footer();
    exit;
}

$allresultssql = "SELECT r.*, c.fullname, c.shortname, cm.id AS cmid
                   FROM {tool_coursecompliance_result} r
                   JOIN {course} c ON c.id = r.courseid
              LEFT JOIN {modules} m ON m.name = r.component
              LEFT JOIN {course_modules} cm ON cm.course = r.courseid
                    AND cm.module = m.id
                    AND cm.instance = r.objectid
                  WHERE r.scanid = :scanid
               ORDER BY c.fullname, r.rulecode";
$allresults = $DB->get_records_sql($allresultssql, ['scanid' => $scanid]);
$missing = [];
$affectedcourses = [];
$mismatchcount = 0;
foreach ($allresults as $result) {
    $affectedcourses[(int)$result->courseid] = true;
    if (\tool_coursecompliance\report_helper::is_missing($result)) {
        $label = \tool_coursecompliance\report_helper::missing_description($result, $config);
        if (!isset($missing[$label])) {
            $missing[$label] = ['count' => 0, 'courses' => []];
        }
        $missing[$label]['count']++;
        $missing[$label]['courses'][(int)$result->courseid] = format_string($result->fullname);
    } else {
        $mismatchcount++;
    }
}
$missingcount = array_sum(array_map(static fn ($item) => $item['count'], $missing));
$totalproblems = (int)$scan->critical + (int)$scan->warnings + (int)$scan->notices;

echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('courses', 'tool_coursecompliance'),
        'value' => (int)$scan->courses,
    ],
    [
        'label' => get_string('affectedcourses', 'tool_coursecompliance'),
        'value' => count($affectedcourses),
    ],
    [
        'label' => get_string('critical', 'tool_coursecompliance'),
        'value' => (int)$scan->critical,
        'class' => (int)$scan->critical > 0 ? 'text-danger' : 'text-success',
    ],
    [
        'label' => get_string('totalproblems', 'tool_coursecompliance'),
        'value' => $totalproblems,
    ],
]);

$severitychart = \tool_coursecompliance\report_charts::severity(
    (int)$scan->critical,
    (int)$scan->warnings,
    (int)$scan->notices
);
$kindchart = \tool_coursecompliance\report_charts::problem_kinds($missingcount, $mismatchcount);
if ($severitychart || $kindchart) {
    echo html_writer::start_div('row g-3 mb-4');
    foreach ([$severitychart, $kindchart] as $chart) {
        if (!$chart) {
            continue;
        }
        echo html_writer::start_div('col-12 col-lg-6');
        echo html_writer::start_div('card shadow-sm h-100');
        echo html_writer::start_div('card-body');
        echo $OUTPUT->render($chart);
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/readiness.php', ['scanid' => $scanid]),
        get_string('openreadiness', 'tool_coursecompliance'),
        ['class' => 'btn btn-primary']
    ),
    'mb-4'
);

if ($missing) {
    echo $OUTPUT->heading(get_string('whatismissing', 'tool_coursecompliance'), 3);
    echo html_writer::div(get_string('whatismissinghelp', 'tool_coursecompliance'), 'text-muted mb-2');
    $missingtable = new html_table();
    $missingtable->attributes['class'] = 'generaltable table-sm';
    $missingtable->head = [
        get_string('missingitem', 'tool_coursecompliance'),
        get_string('count'),
        get_string('affectedcourses', 'tool_coursecompliance'),
    ];
    foreach ($missing as $label => $data) {
        $coursenames = array_values($data['courses']);
        $previewnames = array_slice($coursenames, 0, 5);
        $coursepreview = implode(', ', array_map('s', $previewnames));
        if (count($coursenames) > 5) {
            $coursepreview .= ' … +' . (count($coursenames) - 5);
        }
        $missingtable->data[] = [s($label), (int)$data['count'], $coursepreview];
    }
    echo html_writer::table($missingtable);
}

$allparams = ['scanid' => $scanid];
$baseurl = new moodle_url('/admin/tool/coursecompliance/report.php', $allparams);
$filterlinks = [
    html_writer::link(
        $baseurl,
        get_string('all', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . (($severity === '' && $kind === '') ? 'btn-primary' : 'btn-outline-secondary')]
    ),
    html_writer::link(
        new moodle_url($baseurl, ['severity' => 'critical']),
        get_string('critical', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($severity === 'critical' ? 'btn-danger' : 'btn-outline-danger')]
    ),
    html_writer::link(
        new moodle_url($baseurl, ['severity' => 'warning']),
        get_string('warnings', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($severity === 'warning' ? 'btn-warning' : 'btn-outline-warning')]
    ),
    html_writer::link(
        new moodle_url($baseurl, ['kind' => 'missing']),
        get_string('onlymissing', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm ' . ($kind === 'missing' ? 'btn-secondary' : 'btn-outline-secondary')]
    ),
];

echo $OUTPUT->heading(get_string('detailedproblems', 'tool_coursecompliance'), 3);
echo html_writer::div(implode(' ', $filterlinks), 'mb-3 d-flex flex-wrap gap-2');

$params = ['scanid' => $scanid];
$where = 'r.scanid = :scanid';
if (in_array($severity, ['critical', 'warning', 'notice'], true)) {
    $where .= ' AND r.severity = :severity';
    $params['severity'] = $severity;
}
if (in_array($assessmenttype, ['exam', 'resit1', 'resit2', 'course'], true)) {
    $where .= ' AND r.assessmenttype = :assessmenttype';
    $params['assessmenttype'] = $assessmenttype;
}
if ($courseid > 0) {
    $where .= ' AND r.courseid = :courseid';
    $params['courseid'] = $courseid;
}

$sql = "SELECT r.*, c.fullname, c.shortname, cm.id AS cmid
          FROM {tool_coursecompliance_result} r
          JOIN {course} c ON c.id = r.courseid
     LEFT JOIN {modules} m ON m.name = r.component
     LEFT JOIN {course_modules} cm ON cm.course = r.courseid
           AND cm.module = m.id
           AND cm.instance = r.objectid
         WHERE $where
      ORDER BY CASE r.severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END,
               c.fullname, r.rulecode";
$filtered = $DB->get_records_sql($sql, $params);
if ($kind === 'missing') {
    $filtered = array_filter(
        $filtered,
        static fn ($result) => \tool_coursecompliance\report_helper::is_missing($result)
    );
}
$total = count($filtered);
$results = array_slice(array_values($filtered), $page * $perpage, $perpage);

if (!$results) {
    echo html_writer::div(get_string('noproblemsforfilter', 'tool_coursecompliance'), 'alert alert-success');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    get_string('severity', 'tool_coursecompliance'),
    get_string('course'),
    get_string('whatismissingorwrong', 'tool_coursecompliance'),
    get_string('location', 'tool_coursecompliance'),
    get_string('expected', 'tool_coursecompliance'),
    get_string('actual', 'tool_coursecompliance'),
    get_string('recommendation', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($results as $result) {
    $courseurl = new moodle_url('/course/view.php', ['id' => $result->courseid]);
    $actions = html_writer::link(
        \tool_coursecompliance\report_helper::edit_url($result),
        get_string('openinmoodle', 'tool_coursecompliance')
    );
    if (has_capability('tool/coursecompliance:manageprofiles', $context)) {
        $actions .= '<br>' . html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/exception_add.php', ['resultid' => $result->id]),
            get_string('createexception', 'tool_coursecompliance')
        );
    }
    $problem = \tool_coursecompliance\report_helper::is_missing($result)
        ? \tool_coursecompliance\report_helper::missing_description($result, $config)
        : (string)$result->message;
    $problem .= html_writer::div(
        get_string('rulecode', 'tool_coursecompliance') . ': ' . s($result->rulecode),
        'small text-muted mt-1'
    );
    $table->data[] = [
        \tool_coursecompliance\ui::severity_badge((string)$result->severity),
        html_writer::link($courseurl, format_string($result->fullname)) .
            html_writer::div(s($result->shortname), 'small text-muted'),
        $problem,
        s(\tool_coursecompliance\report_helper::location($result, $config)),
        $result->expected !== '' ? s($result->expected) : '—',
        $result->actual !== '' ? s($result->actual) : '—',
        s(\tool_coursecompliance\report_helper::recommendation($result, $config)),
        $actions,
    ];
}
echo html_writer::table($table);

echo $OUTPUT->paging_bar(
    $total,
    $page,
    $perpage,
    new moodle_url('/admin/tool/coursecompliance/report.php', $urlparams)
);
echo $OUTPUT->footer();
