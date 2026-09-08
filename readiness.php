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
 * Session readiness dashboard page.
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
    $scanid = (int)$DB->get_field_sql("SELECT MAX(id) FROM {tool_cc_scan} WHERE status = 'complete'");
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/readiness.php', ['scanid' => $scanid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('sessionreadiness', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('sessionreadiness', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sessionreadiness', 'tool_coursecompliance'));

if (!$scanid) {
    echo html_writer::div(get_string('nocompletedscans', 'tool_coursecompliance'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$scan = $DB->get_record('tool_cc_scan', ['id' => $scanid, 'status' => 'complete'], '*', MUST_EXIST);
$profile = $DB->get_record('tool_cc_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
$category = \core_course_category::get($scan->categoryid, MUST_EXIST, true);
$states = $DB->get_records('tool_cc_assessment', ['scanid' => $scanid], 'assessmenttype, courseid');

$periods = [];
$scoretotal = 0;
$scorecount = 0;
foreach (['exam', 'resit1', 'resit2'] as $type) {
    $periods[$type] = ['total' => 0, 'ready' => 0, 'warning' => 0, 'critical' => 0, 'missing' => 0, 'score' => 0];
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
    }
    if ((int)$state->critical > 0) {
        $period['critical']++;
    } elseif ((int)$state->warnings > 0) {
        $period['warning']++;
    } else {
        $period['ready']++;
    }
    unset($period);
}
$overallscore = $scorecount ? (int)round($scoretotal / $scorecount) : 100;

echo html_writer::div(
    get_string('readinesscontext', 'tool_coursecompliance', (object)[
        'category' => format_string($category->name),
        'profile' => format_string($profile->name),
        'score' => $overallscore,
    ]),
    $overallscore >= 90 ? 'alert alert-success' : ($overallscore >= 75 ? 'alert alert-warning' : 'alert alert-danger')
);

$table = new html_table();
$table->head = [
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('total', 'tool_coursecompliance'),
    get_string('ready', 'tool_coursecompliance'),
    get_string('warnings', 'tool_coursecompliance'),
    get_string('critical', 'tool_coursecompliance'),
    get_string('missing', 'tool_coursecompliance'),
    get_string('readinessscore', 'tool_coursecompliance'),
];
foreach ($periods as $type => $period) {
    if (!$period['total']) {
        continue;
    }
    $score = (int)round($period['score'] / $period['total']);
    $table->data[] = [
        get_string('period_' . $type, 'tool_coursecompliance'),
        $period['total'],
        $period['ready'],
        $period['warning'],
        $period['critical'],
        $period['missing'],
        $score . '%',
    ];
}
echo html_writer::table($table);

$sql = "SELECT rulecode, severity, COUNT(1) AS issuecount
          FROM {tool_cc_result}
         WHERE scanid = :scanid AND component = 'quiz'
      GROUP BY rulecode, severity
      ORDER BY issuecount DESC";
$problems = $DB->get_records_sql($sql, ['scanid' => $scanid], 0, 15);
if ($problems) {
    echo $OUTPUT->heading(get_string('topassessmentproblems', 'tool_coursecompliance'), 3);
    $problemtable = new html_table();
    $problemtable->head = [
        get_string('severity', 'tool_coursecompliance'),
        get_string('rule', 'tool_coursecompliance'),
        get_string('count'),
    ];
    foreach ($problems as $problem) {
        $problemtable->data[] = [
            get_string('severity_' . $problem->severity, 'tool_coursecompliance'),
            s($problem->rulecode),
            (int)$problem->issuecount,
        ];
    }
    echo html_writer::table($problemtable);
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/assessment.php', ['scanid' => $scanid]),
        get_string('openassessmentauditor', 'tool_coursecompliance'),
        ['class' => 'btn btn-primary me-2']
    ) . ' ' . html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]),
        get_string('viewreport', 'tool_coursecompliance'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-3'
);
echo $OUTPUT->footer();
