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
 * Action Center for current compliance issues.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:view', $context);

$severity = optional_param('severity', '', PARAM_ALPHA);
$kind = optional_param('kind', '', PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_TEXT));
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 100;

$urlparams = [];
if ($severity !== '') {
    $urlparams['severity'] = $severity;
}
if ($kind !== '') {
    $urlparams['kind'] = $kind;
}
if ($search !== '') {
    $urlparams['search'] = $search;
}
if ($page > 0) {
    $urlparams['page'] = $page;
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/action_center.php', $urlparams));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('actioncenter', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('actioncenter', 'tool_coursecompliance'));

// Normal browsing is SQL-paged. The computed "new" and "autofix" views still use
// the current-state helper because those properties depend on profile configuration
// and cross-scan signatures rather than columns stored on the result row.
$metricresults = \tool_coursecompliance\scan_analysis::current_action_results(5000);
$severitycounts = \tool_coursecompliance\action_center_repository::severity_counts();
if ($kind === '') {
    $results = \tool_coursecompliance\action_center_repository::page(
        $severity,
        $search,
        $page,
        $perpage
    );
    $totalfiltered = \tool_coursecompliance\action_center_repository::count($severity, $search);
} else {
    $results = $metricresults;
    $totalfiltered = 0;
}

$configs = [];
$newsignatures = [];
$analysisresults = [];
foreach (array_merge($metricresults, $results) as $result) {
    $analysisresults[(int)$result->id] = $result;
}
foreach ($analysisresults as $result) {
    $scanid = (int)$result->scanid;
    if (!isset($newsignatures[$scanid])) {
        $delta = \tool_coursecompliance\scan_analysis::delta($scanid);
        $newsignatures[$scanid] = array_fill_keys(array_keys($delta['new']), true);
    }
    if (!isset($configs[$scanid])) {
        $configscan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $configs[$scanid] = \tool_coursecompliance\scan_snapshot::config($configscan);
    }
}

$criticalcount = (int)($severitycounts['critical'] ?? 0);
$warningcount = (int)($severitycounts['warning'] ?? 0);
$newcount = 0;
$fixablecount = 0;
foreach ($metricresults as $result) {
    $signature = \tool_coursecompliance\scan_analysis::result_signature($result);
    if (!empty($newsignatures[(int)$result->scanid][$signature])) {
        $newcount++;
    }
    $config = $configs[(int)$result->scanid] ?? [];
    if (\tool_coursecompliance\autofix_manager::is_fixable($result, $config)) {
        $fixablecount++;
    }
}
$failedfixes = (int)$DB->count_records_select(
    'tool_coursecompliance_act_batch',
    "action = :action AND status IN (:failed, :partial) AND timecreated >= :since",
    [
        'action' => 'autofix_scan',
        'failed' => 'failed',
        'partial' => 'partial',
        'since' => time() - WEEKSECS,
    ]
);

$filtered = [];
foreach ($results as $result) {
    $signature = \tool_coursecompliance\scan_analysis::result_signature($result);
    $isnew = !empty($newsignatures[(int)$result->scanid][$signature]);
    $config = $configs[(int)$result->scanid] ?? [];
    $isfixable = \tool_coursecompliance\autofix_manager::is_fixable($result, $config);
    if ($kind === 'new' && !$isnew) {
        continue;
    }
    if ($kind === 'autofix' && !$isfixable) {
        continue;
    }
    if ($kind !== '' && $severity !== '' && $result->severity !== $severity) {
        continue;
    }
    if ($kind !== '' && $search !== '') {
        $haystack = \core_text::strtolower(
            $result->fullname . ' ' . $result->shortname . ' ' . $result->message . ' ' . $result->rulecode
        );
        if (!str_contains($haystack, \core_text::strtolower($search))) {
            continue;
        }
    }
    $result->isnew = $isnew;
    $result->isfixable = $isfixable;
    $filtered[] = $result;
}
if ($kind !== '') {
    $totalfiltered = count($filtered);
    $filtered = array_slice($filtered, $page * $perpage, $perpage);
}

$baseurl = new moodle_url('/admin/tool/coursecompliance/action_center.php');

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('actions');
echo \tool_coursecompliance\ui::page_heading(
    get_string('actioncenter', 'tool_coursecompliance'),
    get_string('actioncenterintro', 'tool_coursecompliance')
);
echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('critical', 'tool_coursecompliance'),
        'value' => $criticalcount,
        'tone' => $criticalcount ? 'danger' : 'success',
        'url' => new moodle_url($baseurl, ['severity' => 'critical']),
    ],
    [
        'label' => get_string('warnings', 'tool_coursecompliance'),
        'value' => $warningcount,
        'tone' => $warningcount ? 'warning' : 'success',
        'url' => new moodle_url($baseurl, ['severity' => 'warning']),
    ],
    [
        'label' => get_string('newissues', 'tool_coursecompliance'),
        'value' => $newcount,
        'tone' => $newcount ? 'danger' : 'success',
        'url' => new moodle_url($baseurl, ['kind' => 'new']),
    ],
    [
        'label' => get_string('autofixable', 'tool_coursecompliance'),
        'value' => $fixablecount,
        'tone' => $fixablecount ? 'info' : 'success',
        'url' => new moodle_url($baseurl, ['kind' => 'autofix']),
    ],
    [
        'label' => get_string('failedautofixesweek', 'tool_coursecompliance'),
        'value' => $failedfixes,
        'tone' => $failedfixes ? 'danger' : 'success',
        'url' => new moodle_url('/admin/tool/coursecompliance/activity_history.php'),
    ],
]);

$filters = [];
$filters[] = html_writer::link(
    $baseurl,
    get_string('all', 'tool_coursecompliance'),
    ['class' => 'btn btn-sm ' . ($severity === '' && $kind === '' ? 'btn-primary' : 'btn-outline-secondary')]
);
$filters[] = html_writer::link(
    new moodle_url($baseurl, ['severity' => 'critical']),
    get_string('critical', 'tool_coursecompliance'),
    ['class' => 'btn btn-sm ' . ($severity === 'critical' ? 'btn-danger' : 'btn-outline-danger')]
);
$filters[] = html_writer::link(
    new moodle_url($baseurl, ['severity' => 'warning']),
    get_string('warnings', 'tool_coursecompliance'),
    ['class' => 'btn btn-sm ' . ($severity === 'warning' ? 'btn-warning' : 'btn-outline-warning')]
);
$filters[] = html_writer::link(
    new moodle_url($baseurl, ['kind' => 'new']),
    get_string('newissues', 'tool_coursecompliance'),
    ['class' => 'btn btn-sm ' . ($kind === 'new' ? 'btn-primary' : 'btn-outline-primary')]
);
$filters[] = html_writer::link(
    new moodle_url($baseurl, ['kind' => 'autofix']),
    get_string('autofixable', 'tool_coursecompliance'),
    ['class' => 'btn btn-sm ' . ($kind === 'autofix' ? 'btn-primary' : 'btn-outline-primary')]
);

$filterbody = html_writer::div(implode(' ', $filters), 'd-flex flex-wrap gap-2 mb-3');
$filterbody .= html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex gap-2']);
$filterbody .= html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'search',
    'value' => $search,
    'class' => 'form-control',
    'placeholder' => get_string('searchcoursesissues', 'tool_coursecompliance'),
]);
$filterbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'severity', 'value' => $severity]);
$filterbody .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'kind', 'value' => $kind]);
$filterbody .= html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-outline-primary',
    'value' => get_string('search'),
]);
$filterbody .= html_writer::end_tag('form');
echo \tool_coursecompliance\ui::panel(get_string('filters', 'tool_coursecompliance'), $filterbody);

if ($totalfiltered > $perpage) {
    echo $OUTPUT->paging_bar($totalfiltered, $page, $perpage, $PAGE->url);
}

if (!$filtered) {
    echo \tool_coursecompliance\ui::panel(
        get_string('currentissues', 'tool_coursecompliance'),
        html_writer::div(get_string('actioncenterclear', 'tool_coursecompliance'), 'cc-empty-state text-success')
    );
    echo $OUTPUT->footer();
    exit;
}

$canfix = has_capability('tool/coursecompliance:manageactivities', $context);
$canexcept = has_capability('tool/coursecompliance:manageprofiles', $context);
$questiondetailcounts = \tool_coursecompliance\question_issue_repository::counts_for_results($filtered);
$action = new moodle_url('/admin/tool/coursecompliance/autofix_selected.php');
$filteredfixable = count(array_filter($filtered, static fn ($result): bool => !empty($result->isfixable)));
$body = '';
if ($canfix) {
    $body .= html_writer::start_tag('form', ['method' => 'post', 'action' => $action->out(false)]);
    $body .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $body .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'returnurl',
        'value' => $PAGE->url->out(false),
    ]);
}
$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle cc-action-table';
$table->head = [
    $canfix ? get_string('select') : '',
    get_string('severity', 'tool_coursecompliance'),
    get_string('course'),
    get_string('problem', 'tool_coursecompliance'),
    get_string('standard', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($filtered as $result) {
    $select = '';
    if ($canfix && !empty($result->isfixable)) {
        $select = html_writer::checkbox('resultids[]', (int)$result->id, false, '', ['aria-label' => get_string('select')]);
    }
    $severityhtml = \tool_coursecompliance\ui::severity_badge((string)$result->severity);
    if (!empty($result->isnew)) {
        $severityhtml .= '<br>' . html_writer::span(get_string('newissue', 'tool_coursecompliance'), 'badge bg-primary mt-1');
    }
    if (!empty($result->isfixable)) {
        $severityhtml .= '<br>' . html_writer::span(
            get_string('autofixavailable', 'tool_coursecompliance'),
            'badge bg-info text-dark mt-1'
        );
    }
    $coursehtml = html_writer::link(
        new moodle_url('/course/view.php', ['id' => (int)$result->courseid]),
        format_string($result->fullname)
    );
    $coursehtml .= html_writer::div(s((string)$result->shortname), 'small text-muted');
    $problemhtml = s((string)$result->message);
    if ((string)$result->actual !== '' || (string)$result->expected !== '') {
        $problemhtml .= html_writer::div(
            get_string('expectedactualcompact', 'tool_coursecompliance', (object)[
                'expected' => (string)$result->expected,
                'actual' => (string)$result->actual,
            ]),
            'small text-muted mt-1'
        );
    }
    $actions = html_writer::link(
        \tool_coursecompliance\report_helper::edit_url($result),
        get_string('openinmoodle', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm btn-outline-primary me-1']
    );
    $actions .= html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/course_issues.php', ['courseid' => (int)$result->courseid]),
        get_string('courseissues', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm btn-outline-secondary me-1']
    );
    $questiondetailcount = $questiondetailcounts[(int)$result->id] ?? 0;
    if ($questiondetailcount > 0) {
        $actions .= html_writer::link(
            \tool_coursecompliance\question_issue_repository::url_for_result($result),
            get_string('showproblemquestions', 'tool_coursecompliance', $questiondetailcount),
            ['class' => 'btn btn-sm btn-outline-danger me-1']
        );
    }
    if ($canexcept) {
        $actions .= html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/exception_add.php', ['resultid' => (int)$result->id]),
            get_string('createexception', 'tool_coursecompliance'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        );
    }
    $table->data[] = [
        $select,
        $severityhtml,
        $coursehtml,
        $problemhtml,
        format_string((string)$result->profilename),
        $actions,
    ];
}
$body .= html_writer::table($table);
if ($canfix) {
    if ($filteredfixable > 0) {
        $body .= html_writer::div(
            html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'value' => get_string('prepareselectedautofix', 'tool_coursecompliance'),
            ]),
            'mt-3'
        );
    }
    $body .= html_writer::end_tag('form');
}
echo \tool_coursecompliance\ui::panel(
    get_string('currentissuescount', 'tool_coursecompliance', $totalfiltered),
    $body,
    get_string('actioncenterlisthelp', 'tool_coursecompliance')
);
if ($totalfiltered > $perpage) {
    echo $OUTPUT->paging_bar($totalfiltered, $page, $perpage, $PAGE->url);
}

echo $OUTPUT->footer();
