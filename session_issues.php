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
 * Session planning issues page.
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
$perpage = 100;
$batch = $DB->get_record('tool_cc_act_batch', ['id' => $batchid], '*', MUST_EXIST);
if ($batch->action !== 'session_schedule') {
    throw new moodle_exception('invalidoperation', 'tool_coursecompliance');
}
$total = $DB->count_records('tool_cc_session_issue', ['batchid' => $batchid]);
$issues = $DB->get_records(
    'tool_cc_session_issue',
    ['batchid' => $batchid],
    'courseid ASC, assessmenttype ASC, modname ASC',
    '*',
    $page * $perpage,
    $perpage
);
$courseids = array_values(array_unique(array_map(static fn ($issue) => (int)$issue->courseid, $issues)));
$courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id,fullname') : [];

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/session_issues.php', ['batchid' => $batchid, 'page' => $page]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('sessionissues', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('sessionissues', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sessionissues', 'tool_coursecompliance') . ' #' . $batchid);
$table = new html_table();
$table->head = [
    get_string('course'),
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('activitytype', 'tool_coursecompliance'),
    'idnumber',
    get_string('problem', 'tool_coursecompliance'),
];
foreach ($issues as $issue) {
    $course = $courses[$issue->courseid] ?? null;
    $problem = get_string('sessionissue_' . $issue->issuecode, 'tool_coursecompliance', $issue->details);
    $table->data[] = [
        $course
            ? html_writer::link(
                new moodle_url('/course/view.php', ['id' => $course->id]),
                format_string($course->fullname)
            )
            : '#' . $issue->courseid,
        get_string('period_' . $issue->assessmenttype, 'tool_coursecompliance'),
        get_string('modulename', $issue->modname),
        s($issue->idnumber),
        s($problem),
    ];
}
echo html_writer::table($table);
$issuesurl = new moodle_url('/admin/tool/coursecompliance/session_issues.php', ['batchid' => $batchid]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $issuesurl);
$batchurl = new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]);
echo html_writer::div(
    html_writer::link($batchurl, get_string('backtobatch', 'tool_coursecompliance')),
    'mt-3'
);
echo $OUTPUT->footer();
