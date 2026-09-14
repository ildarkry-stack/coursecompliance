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
 * Detailed question-bank issues behind one aggregate audit result.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:view', $context);

$resultid = required_param('resultid', PARAM_INT);
$result = $DB->get_record('tool_coursecompliance_result', ['id' => $resultid], '*', MUST_EXIST);
$course = get_course((int)$result->courseid);
$issues = \tool_coursecompliance\question_issue_repository::for_result($result);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/question_issues.php', ['resultid' => $resultid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('questionissuedetails', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('questionissuedetails', 'tool_coursecompliance'));

$period = in_array($result->assessmenttype, ['exam', 'resit1', 'resit2'], true)
    ? get_string('period_' . $result->assessmenttype, 'tool_coursecompliance')
    : get_string('course');

$critical = 0;
$warning = 0;
$notice = 0;
$questionids = [];
foreach ($issues as $issue) {
    if ($issue->severity === 'critical') {
        $critical++;
    } else if ($issue->severity === 'warning') {
        $warning++;
    } else {
        $notice++;
    }
    if (!empty($issue->questionid)) {
        $questionids[(int)$issue->questionid] = true;
    }
}

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('questions');
echo \tool_coursecompliance\ui::page_heading(
    get_string('questionissuedetails', 'tool_coursecompliance'),
    get_string('questionissuedetailsintro', 'tool_coursecompliance', (object)[
        'course' => format_string($course->fullname),
        'period' => $period,
    ])
);
echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('problemquestions', 'tool_coursecompliance'),
        'value' => count($questionids),
        'tone' => count($questionids) ? 'danger' : 'success',
    ],
    [
        'label' => get_string('critical', 'tool_coursecompliance'),
        'value' => $critical,
        'tone' => $critical ? 'danger' : 'success',
    ],
    [
        'label' => get_string('warnings', 'tool_coursecompliance'),
        'value' => $warning,
        'tone' => $warning ? 'warning' : 'success',
    ],
    [
        'label' => get_string('notices', 'tool_coursecompliance'),
        'value' => $notice,
        'tone' => $notice ? 'info' : 'success',
    ],
]);

$topactions = html_writer::link(
    new moodle_url('/course/view.php', ['id' => (int)$course->id]),
    get_string('opencourse', 'tool_coursecompliance'),
    ['class' => 'btn btn-outline-secondary me-2']
);
$topactions .= html_writer::link(
    \tool_coursecompliance\question_issue_repository::question_bank_url((int)$course->id),
    get_string('openquestionbank', 'tool_coursecompliance'),
    ['class' => 'btn btn-outline-primary me-2']
);
$topactions .= html_writer::link(
    new moodle_url('/admin/tool/coursecompliance/question_bank.php'),
    get_string('allquestionproblems', 'tool_coursecompliance'),
    ['class' => 'btn btn-outline-primary']
);
echo html_writer::div($topactions, 'mb-4');

if (!$issues) {
    echo $OUTPUT->notification(get_string('questiondetailsnotavailable', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm align-middle';
$table->head = [
    get_string('severity', 'tool_coursecompliance'),
    get_string('question', 'tool_coursecompliance'),
    get_string('questiontype', 'tool_coursecompliance'),
    get_string('questioncategory', 'tool_coursecompliance'),
    get_string('problem', 'tool_coursecompliance'),
    get_string('details', 'tool_coursecompliance'),
    get_string('actions'),
];
$returnurl = $PAGE->url->out(false);
foreach ($issues as $issue) {
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
    $details = \tool_coursecompliance\question_issue_repository::details_text($issue);
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
        ['class' => 'btn btn-sm btn-outline-secondary me-1']
    );
    $quizurl = \tool_coursecompliance\question_issue_repository::quiz_url($issue);
    if ($quizurl) {
        $actions .= html_writer::link(
            $quizurl,
            get_string('openquizstructure', 'tool_coursecompliance'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        );
    }
    $table->data[] = [
        \tool_coursecompliance\ui::severity_badge((string)$issue->severity),
        $question,
        $issue->qtype !== '' ? s((string)$issue->qtype) : '—',
        $issue->categoryname !== '' ? format_string($issue->categoryname) : '—',
        s(\tool_coursecompliance\question_issue_repository::issue_label((string)$issue->issuecode)),
        $details !== '' ? s($details) : '—',
        $actions,
    ];
}

echo \tool_coursecompliance\ui::panel(
    get_string('questionproblemscount', 'tool_coursecompliance', count($issues)),
    html_writer::table($table),
    get_string('questionissueshelp', 'tool_coursecompliance')
);

echo $OUTPUT->footer();
