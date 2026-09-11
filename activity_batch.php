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
 * Activity operation batch preview page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:view', $systemcontext);

$batchid = required_param('batchid', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = optional_param('perpage', 100, PARAM_INT);
$perpage = in_array($perpage, [50, 100, 200], true) ? $perpage : 100;
$batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid], '*', MUST_EXIST);
if ((int)$batch->userid !== (int)$USER->id && !is_siteadmin()) {
    require_capability('tool/coursecompliance:view', $systemcontext);
}
$canmanagebatch = \tool_coursecompliance\local\batch_access::can_manage($batch);

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/activity_batch.php', [
    'batchid' => $batchid,
    'page' => $page,
    'perpage' => $perpage,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('operationpreview', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('operationpreview', 'tool_coursecompliance'));

$totalchanges = $DB->count_records('tool_coursecompliance_act_change', ['batchid' => $batchid]);
$changes = [];
$courses = [];
$activities = [];
if ($batch->action !== 'session_schedule') {
    $changes = $DB->get_records(
        'tool_coursecompliance_act_change',
        ['batchid' => $batchid],
        'courseid ASC, cmid ASC, id ASC',
        '*',
        $page * $perpage,
        $perpage
    );
    $courseids = array_values(array_unique(array_map(static fn ($c) => (int)$c->courseid, $changes)));
    $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id,fullname') : [];
    $cmids = array_values(array_unique(array_map(static fn ($c) => (int)$c->cmid, $changes)));
    $activities = $cmids ? (new \tool_coursecompliance\activity_repository())->get_by_cmids($cmids) : [];
}
$batchconfig = json_decode((string)$batch->filterjson, true) ?: [];

/**
 * Format a previewed change value for display.
 *
 * @param \stdClass $change Change record.
 * @param string $value Stored value.
 * @return string Display value.
 */
function tool_coursecompliance_format_change_value(\stdClass $change, string $value): string {
    $datefields = [
        'timeopen',
        'timeclose',
        'allowsubmissionsfromdate',
        'duedate',
        'cutoffdate',
        'gradingduedate',
    ];
    if (in_array($change->fieldname, $datefields, true)) {
        $timestamp = (int)$value;
        return $timestamp ? userdate($timestamp) : '—';
    }
    if ($change->fieldname === 'visibilitystate') {
        return get_string('visibility_' . $value, 'tool_coursecompliance');
    }
    if ($change->fieldname === 'timelimit') {
        return (int)$value > 0 ? format_time((int)$value) : get_string('none', 'tool_coursecompliance');
    }
    if ($change->fieldname === 'attempts') {
        return (int)$value > 0 ? (string)(int)$value : get_string('unlimited', 'tool_coursecompliance');
    }
    if ($change->fieldname === 'grade') {
        return format_float((float)$value, 2);
    }
    if ($change->fieldname === 'shuffleanswers') {
        return get_string((int)$value ? 'enabled' : 'disabled', 'tool_coursecompliance');
    }
    if ($change->fieldname === 'browsersecurity') {
        return trim($value) !== '' ? s($value) : get_string('none', 'tool_coursecompliance');
    }
    if (str_starts_with((string)$change->fieldname, 'review')) {
        $periods = [];
        $mask = (int)$value;
        foreach ([
            0x10000 => get_string('reviewperiod_during', 'tool_coursecompliance'),
            0x1000 => get_string('reviewperiod_immediately', 'tool_coursecompliance'),
            0x100 => get_string('reviewperiod_open', 'tool_coursecompliance'),
            0x10 => get_string('reviewperiod_closed', 'tool_coursecompliance'),
        ] as $bit => $label) {
            if (($mask & $bit) !== 0) {
                $periods[] = $label;
            }
        }
        return $periods ? s(implode(', ', $periods)) : get_string('disabled', 'tool_coursecompliance');
    }
    return s($value);
}

/**
 * Resolve session period from batch config and current activity.
 *
 * @param array $batchconfig Decoded batch filter payload.
 * @param \stdClass|null $activity Activity record.
 * @return string
 */
function tool_coursecompliance_session_period(array $batchconfig, ?\stdClass $activity): string {
    if (!$activity || empty($batchconfig['periods'])) {
        return '';
    }
    foreach ($batchconfig['periods'] as $period => $config) {
        $key = $activity->modname . 'idnumber';
        if (!empty($config[$key]) && (string)$config[$key] === (string)$activity->idnumber) {
            return get_string('period_' . $period, 'tool_coursecompliance');
        }
    }
    return '';
}

/**
 * Build completed-operation metric cards.
 *
 * @param int $batchid Batch id.
 * @param \stdClass $batch Batch record.
 * @param array $batchconfig Batch configuration.
 * @return string HTML.
 */
function tool_coursecompliance_batch_result_metrics(int $batchid, \stdClass $batch, array $batchconfig): string {
    global $DB;

    $coursecount = (int)$DB->count_records_sql(
        'SELECT COUNT(DISTINCT courseid) FROM {tool_coursecompliance_act_change} WHERE batchid = :batchid',
        ['batchid' => $batchid]
    );
    $elementcounts = ['applied' => 0, 'skipped' => 0, 'failed' => 0];
    $rows = $DB->get_records_sql(
        'SELECT status, COUNT(DISTINCT cmid) AS elementcount
           FROM {tool_coursecompliance_act_change}
          WHERE batchid = :batchid
       GROUP BY status',
        ['batchid' => $batchid]
    );
    foreach ($rows as $row) {
        if (array_key_exists((string)$row->status, $elementcounts)) {
            $elementcounts[(string)$row->status] = (int)$row->elementcount;
        }
    }

    $metrics = [
        [
            'label' => get_string('processedcourses', 'tool_coursecompliance'),
            'value' => $coursecount,
        ],
        [
            'label' => get_string('changedelements', 'tool_coursecompliance'),
            'value' => $elementcounts['applied'],
            'class' => $elementcounts['applied'] > 0 ? 'text-success' : '',
        ],
        [
            'label' => get_string('skippedelements', 'tool_coursecompliance'),
            'value' => $elementcounts['skipped'],
            'class' => $elementcounts['skipped'] > 0 ? 'text-warning' : '',
        ],
        [
            'label' => get_string('failedelements', 'tool_coursecompliance'),
            'value' => $elementcounts['failed'],
            'class' => $elementcounts['failed'] > 0 ? 'text-danger' : 'text-success',
        ],
    ];

    if ($batch->action === 'session_schedule') {
        $issues = (int)$DB->count_records('tool_coursecompliance_session_issue', ['batchid' => $batchid]);
        $metrics[] = [
            'label' => get_string('notfoundorambiguous', 'tool_coursecompliance'),
            'value' => $issues,
            'class' => $issues > 0 ? 'text-danger' : 'text-success',
        ];

        $periods = $batchconfig['periods'] ?? [];
        $targetpercourse = 0;
        foreach (['exam', 'resit1', 'resit2'] as $period) {
            foreach (['quiz', 'assign'] as $modname) {
                if (!empty($periods[$period][$modname . 'idnumber'])) {
                    $targetpercourse++;
                }
            }
        }
        if ($targetpercourse > 0) {
            $category = \core_course_category::get((int)$batch->categoryid, MUST_EXIST, true);
            $categoryids = [(int)$batch->categoryid];
            if (!empty($batchconfig['includesubcategories'])) {
                $categoryids = array_merge($categoryids, $category->get_all_children_ids());
            }
            [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'metriccat');
            $catparams['siteid'] = SITEID;
            $courses = (int)$DB->count_records_select('course', "category $catsql AND id <> :siteid", $catparams);
            $found = max(0, ($courses * $targetpercourse) - $issues);
            $changedtargets = (int)$DB->count_records_sql(
                'SELECT COUNT(DISTINCT cmid) FROM {tool_coursecompliance_act_change} WHERE batchid = :batchid',
                ['batchid' => $batchid]
            );
            $unchanged = max(0, $found - $changedtargets);
            $metrics[] = [
                'label' => get_string('unchangedelements', 'tool_coursecompliance'),
                'value' => $unchanged,
                'class' => 'text-success',
            ];
        }
    }

    return \tool_coursecompliance\ui::metric_cards($metrics);
}

echo $OUTPUT->header();
$primarynav = $batch->action === 'session_schedule' ? 'session' : 'operations';
echo \tool_coursecompliance\ui::primary_navigation($primarynav);
if ($primarynav === 'operations') {
    echo \tool_coursecompliance\ui::operations_navigation('history');
}
echo $OUTPUT->heading(get_string('operationpreview', 'tool_coursecompliance') . ' #' . $batch->id);
echo html_writer::div(s($batch->summary), 'alert alert-info');
if ($batch->status !== 'preview') {
    echo \tool_coursecompliance\ui::batch_status_panel($batch);
}
if (in_array($batch->status, ['complete', 'partial', 'failed'], true)) {
    echo tool_coursecompliance_batch_result_metrics($batchid, $batch, $batchconfig);
}

if ($batch->action === 'session_schedule') {
    $statusline = get_string('status', 'tool_coursecompliance') . ': ' .
        \tool_coursecompliance\ui::status_badge((string)$batch->status, 'batchstatus');
    $statusline .= ' · ' . get_string('changes', 'tool_coursecompliance') . ': ' . (int)$batch->totalchanges;
    if (in_array($batch->status, ['complete', 'partial', 'failed'], true)) {
        $statusline .= ' · ' . get_string('applied', 'tool_coursecompliance') . ': ' . (int)$batch->applied;
        $statusline .= ' · ' . get_string('skipped', 'tool_coursecompliance') . ': ' . (int)$batch->skipped;
        $statusline .= ' · ' . get_string('failed', 'tool_coursecompliance') . ': ' . (int)$batch->failed;
    }
    echo html_writer::div($statusline, 'mb-3');
    echo \tool_coursecompliance\session_preview_report::render($batch, $batchconfig, $page, $perpage);
} else {
    $summary = new html_table();
    $summary->data = [
        [get_string('status', 'tool_coursecompliance'), get_string('batchstatus_' . $batch->status, 'tool_coursecompliance')],
        [get_string('operation', 'tool_coursecompliance'), get_string('operation_' . $batch->action, 'tool_coursecompliance')],
        [get_string('changes', 'tool_coursecompliance'), $batch->totalchanges],
        [get_string('applied', 'tool_coursecompliance'), $batch->applied],
        [get_string('skipped', 'tool_coursecompliance'), $batch->skipped],
        [get_string('failed', 'tool_coursecompliance'), $batch->failed],
    ];
    if (!empty($batch->sourcebatchid)) {
        $summary->data[] = [
            get_string('sourcebatch', 'tool_coursecompliance'),
            html_writer::link(
                new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batch->sourcebatchid]),
                '#' . $batch->sourcebatchid
            ),
        ];
    }
    echo html_writer::table($summary);

    if ($totalchanges) {
        $table = new html_table();
        $table->head = [
            get_string('course'),
            get_string('activity', 'tool_coursecompliance'),
            get_string('field', 'tool_coursecompliance'),
            get_string('before', 'tool_coursecompliance'),
            get_string('after', 'tool_coursecompliance'),
            get_string('status', 'tool_coursecompliance'),
            get_string('message', 'tool_coursecompliance'),
        ];
        foreach ($changes as $change) {
            $course = $courses[$change->courseid] ?? null;
            $activity = $activities[$change->cmid] ?? null;
            $courseurl = new moodle_url('/course/view.php', ['id' => $change->courseid]);
            $activitylabel = $activity
                ? format_string($activity->activityname) . html_writer::tag(
                    'small',
                    get_string('modulename', $activity->modname) .
                        ($activity->idnumber ? ' · ' . s($activity->idnumber) : '') .
                        (($period = tool_coursecompliance_session_period($batchconfig, $activity)) ? ' · ' . s($period) : ''),
                    ['class' => 'text-muted d-block']
                )
                : s($change->modname) . ' #' . $change->cmid;
            $table->data[] = [
                $course ? html_writer::link($courseurl, format_string($course->fullname)) : (string)$change->courseid,
                $activitylabel,
                get_string('field_' . $change->fieldname, 'tool_coursecompliance'),
                tool_coursecompliance_format_change_value($change, $change->oldvalue),
                tool_coursecompliance_format_change_value($change, $change->newvalue),
                get_string('changestatus_' . $change->status, 'tool_coursecompliance'),
                s($change->message),
            ];
        }
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar(
            $totalchanges,
            $page,
            $perpage,
            new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid, 'perpage' => $perpage])
        );
    } else {
        echo $OUTPUT->notification(get_string('batchhasnochanges', 'tool_coursecompliance'), 'info');
    }
}

if ($batch->status === 'preview' && (int)$batch->totalchanges > 0 && $canmanagebatch) {
    $applyurl = new moodle_url('/admin/tool/coursecompliance/activity_apply.php');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $applyurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchid', 'value' => $batch->id]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-danger',
        'value' => get_string('confirmapply', 'tool_coursecompliance'),
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::div(get_string('previewconflictnote', 'tool_coursecompliance'), 'text-muted mt-2');
} else if (in_array($batch->status, ['queued', 'running'], true)) {
    echo html_writer::div(get_string('batchrunning', 'tool_coursecompliance'), 'alert alert-info');
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]),
        get_string('refresh')
    );
} else if ($batch->status === 'failed' && $batch->errormessage) {
    echo html_writer::div(s($batch->errormessage), 'alert alert-danger');
}

if ($batch->action === 'autofix_scan' && in_array($batch->status, ['complete', 'partial'], true)) {
    echo html_writer::div(get_string('autofixrescanrequired', 'tool_coursecompliance'), 'alert alert-info mt-3');
}

if (
    in_array($batch->status, ['complete', 'partial'], true)
    && $batch->action !== 'rollback'
    && (int)$batch->applied > 0
    && $canmanagebatch
) {
    $rollbackurl = new moodle_url('/admin/tool/coursecompliance/activity_rollback.php');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $rollbackurl->out(false), 'class' => 'mt-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'batchid', 'value' => $batch->id]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-outline-danger',
        'value' => get_string('preparerollback', 'tool_coursecompliance'),
    ]);
    echo html_writer::end_tag('form');
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/activity_history.php'),
        get_string('operationhistory', 'tool_coursecompliance')
    ),
    'mt-3'
);
if ($batch->action === 'session_schedule') {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/session.php'),
            get_string('backtosessionplanning', 'tool_coursecompliance')
        ),
        'mt-2'
    );
}
echo $OUTPUT->footer();
