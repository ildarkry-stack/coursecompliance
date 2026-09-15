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
 * Question-bank quality dashboard.
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
$issuecode = optional_param('issuecode', '', PARAM_ALPHANUMEXT);
$assessmenttype = optional_param('assessmenttype', '', PARAM_ALPHANUMEXT);
$qtype = optional_param('qtype', '', PARAM_ALPHANUMEXT);
$search = trim(optional_param('search', '', PARAM_TEXT));
$area = optional_param('area', '', PARAM_ALPHA);
$inaudit = $area === 'audit';

$params = [];
foreach (['severity', 'issuecode', 'assessmenttype', 'qtype', 'search'] as $name) {
    if ($$name !== '') {
        $params[$name] = $$name;
    }
}
if ($inaudit) {
    $params['area'] = 'audit';
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/question_bank.php', $params));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('questionbankquality', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('questionbankquality', 'tool_coursecompliance'));

$issues = \tool_coursecompliance\question_issue_repository::current_issues(10000);
$allissues = $issues;
$questionids = [];
$criticalcount = 0;
$nocorrectcount = 0;
$brokenorpartial = 0;
$issueoptions = [];
$qtypeoptions = [];
foreach ($allissues as $issue) {
    if (!empty($issue->questionid)) {
        $questionids[(int)$issue->questionid] = true;
    }
    if ($issue->severity === 'critical') {
        $criticalcount++;
    }
    if ($issue->issuecode === 'no_correct_answer') {
        $nocorrectcount++;
    }
    if (in_array($issue->issuecode, ['broken_question', 'correct_answer_unchecked', 'structure_error'], true)) {
        $brokenorpartial++;
    }
    $issueoptions[(string)$issue->issuecode] = \tool_coursecompliance\question_issue_repository::issue_label(
        (string)$issue->issuecode
    );
    if ($issue->qtype !== '') {
        $qtypeoptions[(string)$issue->qtype] = (string)$issue->qtype;
    }
}
asort($issueoptions);
asort($qtypeoptions);

$filtered = [];
foreach ($issues as $issue) {
    if ($severity !== '' && $issue->severity !== $severity) {
        continue;
    }
    if ($issuecode !== '' && $issue->issuecode !== $issuecode) {
        continue;
    }
    if ($assessmenttype !== '' && $issue->assessmenttype !== $assessmenttype) {
        continue;
    }
    if ($qtype !== '' && $issue->qtype !== $qtype) {
        continue;
    }
    if ($search !== '') {
        $haystack = \core_text::strtolower(
            $issue->fullname . ' ' . $issue->shortname . ' ' . $issue->questionname . ' ' .
            $issue->categoryname . ' ' . $issue->quizname
        );
        if (!str_contains($haystack, \core_text::strtolower($search))) {
            continue;
        }
    }
    $filtered[] = $issue;
}

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation($inaudit ? 'audit' : 'questions');
if ($inaudit) {
    echo \tool_coursecompliance\ui::audit_navigation('questions');
}
echo \tool_coursecompliance\ui::page_heading(
    get_string('questionbankquality', 'tool_coursecompliance'),
    get_string('questionbankqualityintro', 'tool_coursecompliance')
);
echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('problemquestions', 'tool_coursecompliance'),
        'value' => count($questionids),
        'tone' => count($questionids) ? 'danger' : 'success',
    ],
    [
        'label' => get_string('criticalquestionissues', 'tool_coursecompliance'),
        'value' => $criticalcount,
        'tone' => $criticalcount ? 'danger' : 'success',
    ],
    [
        'label' => get_string('questionswithoutcorrectanswer', 'tool_coursecompliance'),
        'value' => $nocorrectcount,
        'tone' => $nocorrectcount ? 'danger' : 'success',
    ],
    [
        'label' => get_string('questionchecksincomplete', 'tool_coursecompliance'),
        'value' => $brokenorpartial,
        'tone' => $brokenorpartial ? 'warning' : 'success',
    ],
]);

$filterbody = html_writer::start_tag('form', ['method' => 'get', 'class' => 'row g-2 align-items-end']);
if ($inaudit) {
    $filterbody .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'area',
        'value' => 'audit',
    ]);
}
$filterbody .= html_writer::div(
    html_writer::label(get_string('severity', 'tool_coursecompliance'), 'id_severity', false, ['class' => 'form-label']) .
    html_writer::select([
        '' => get_string('all', 'tool_coursecompliance'),
        'critical' => get_string('severity_critical', 'tool_coursecompliance'),
        'warning' => get_string('severity_warning', 'tool_coursecompliance'),
        'notice' => get_string('severity_notice', 'tool_coursecompliance'),
    ], 'severity', $severity, false, ['id' => 'id_severity', 'class' => 'form-select']),
    'col-12 col-md-2'
);
$filterbody .= html_writer::div(
    html_writer::label(get_string('problem', 'tool_coursecompliance'), 'id_issuecode', false, ['class' => 'form-label']) .
    html_writer::select(
        ['' => get_string('all', 'tool_coursecompliance')] + $issueoptions,
        'issuecode',
        $issuecode,
        false,
        ['id' => 'id_issuecode', 'class' => 'form-select']
    ),
    'col-12 col-md-3'
);
$filterbody .= html_writer::div(
    html_writer::label(
        get_string('assessmentperiod', 'tool_coursecompliance'),
        'id_assessmenttype',
        false,
        ['class' => 'form-label']
    ) .
    html_writer::select([
        '' => get_string('all', 'tool_coursecompliance'),
        'exam' => get_string('period_exam', 'tool_coursecompliance'),
        'resit1' => get_string('period_resit1', 'tool_coursecompliance'),
        'resit2' => get_string('period_resit2', 'tool_coursecompliance'),
    ], 'assessmenttype', $assessmenttype, false, ['id' => 'id_assessmenttype', 'class' => 'form-select']),
    'col-12 col-md-2'
);
$filterbody .= html_writer::div(
    html_writer::label(get_string('questiontype', 'tool_coursecompliance'), 'id_qtype', false, ['class' => 'form-label']) .
    html_writer::select(
        ['' => get_string('all', 'tool_coursecompliance')] + $qtypeoptions,
        'qtype',
        $qtype,
        false,
        ['id' => 'id_qtype', 'class' => 'form-select']
    ),
    'col-12 col-md-2'
);
$filterbody .= html_writer::div(
    html_writer::label(get_string('search'), 'id_search', false, ['class' => 'form-label']) .
    html_writer::empty_tag('input', [
        'type' => 'search',
        'name' => 'search',
        'id' => 'id_search',
        'value' => $search,
        'class' => 'form-control',
        'placeholder' => get_string('questionbanksearch', 'tool_coursecompliance'),
    ]),
    'col-12 col-md-2'
);
$filterbody .= html_writer::div(
    html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary w-100',
        'value' => get_string('applyfilters', 'tool_coursecompliance'),
    ]),
    'col-12 col-md-1'
);
$filterbody .= html_writer::end_tag('form');
echo \tool_coursecompliance\ui::panel(get_string('filters', 'tool_coursecompliance'), $filterbody);

if (!$filtered) {
    echo $OUTPUT->notification(get_string('questionbankissuesclear', 'tool_coursecompliance'), 'success');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle';
$table->head = [
    get_string('severity', 'tool_coursecompliance'),
    get_string('course'),
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('question', 'tool_coursecompliance'),
    get_string('questioncategory', 'tool_coursecompliance'),
    get_string('problem', 'tool_coursecompliance'),
    get_string('actions'),
];
$returnurl = $PAGE->url->out(false);
foreach ($filtered as $issue) {
    $coursehtml = html_writer::link(
        new moodle_url('/course/view.php', ['id' => (int)$issue->courseid]),
        format_string($issue->fullname)
    );
    $coursehtml .= html_writer::div(s((string)$issue->shortname), 'small text-muted');
    $period = in_array($issue->assessmenttype, ['exam', 'resit1', 'resit2'], true)
        ? get_string('period_' . $issue->assessmenttype, 'tool_coursecompliance')
        : (string)$issue->assessmenttype;
    $question = $issue->questionname !== ''
        ? format_string($issue->questionname)
        : get_string('questionobjectnotavailable', 'tool_coursecompliance');
    if (!empty($issue->questionid)) {
        $question .= html_writer::div('ID ' . (int)$issue->questionid, 'small text-muted');
    } else if (!empty($issue->slotnumber)) {
        $question .= html_writer::div(
            get_string('quizslotnumber', 'tool_coursecompliance', (int)$issue->slotnumber),
            'small text-muted'
        );
    }
    if ($issue->qtype !== '') {
        $question .= html_writer::div(s((string)$issue->qtype), 'small text-muted');
    }
    $problem = s(\tool_coursecompliance\question_issue_repository::issue_label((string)$issue->issuecode));
    $detail = \tool_coursecompliance\question_issue_repository::details_text($issue);
    if ($detail !== '') {
        $problem .= html_writer::div(s($detail), 'small text-muted mt-1');
    }
    $actions = '';
    $editurl = \tool_coursecompliance\question_issue_repository::question_edit_url($issue, $returnurl);
    if ($editurl) {
        $actions .= html_writer::link(
            $editurl,
            get_string('editquestion', 'question'),
            ['class' => 'btn btn-sm btn-outline-primary me-1']
        );
    }
    $actions .= html_writer::link(
        \tool_coursecompliance\question_issue_repository::question_bank_url((int)$issue->courseid),
        get_string('openquestionbank', 'tool_coursecompliance'),
        ['class' => 'btn btn-sm btn-outline-secondary']
    );
    $table->data[] = [
        \tool_coursecompliance\ui::severity_badge((string)$issue->severity),
        $coursehtml,
        $period,
        $question,
        $issue->categoryname !== '' ? format_string($issue->categoryname) : '—',
        $problem,
        $actions,
    ];
}

echo \tool_coursecompliance\ui::panel(
    get_string('questionproblemscount', 'tool_coursecompliance', count($filtered)),
    html_writer::table($table),
    get_string('questionbankdashboardhelp', 'tool_coursecompliance')
);

echo $OUTPUT->footer();
