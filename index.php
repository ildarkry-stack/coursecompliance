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
 * Course compliance dashboard page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('pluginname', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\scan_form();
if ($data = $form->get_data()) {
    require_capability('tool/coursecompliance:scan', $context);
    $profileid = (int)$data->profileid;
    $profileexists = $profileid > 0 && $DB->record_exists(
        'tool_coursecompliance_profile',
        ['id' => $profileid, 'enabled' => 1]
    );
    if (!$profileexists) {
        throw new moodle_exception('profilemustbeselected', 'tool_coursecompliance');
    }
    $scanid = $DB->insert_record('tool_coursecompliance_scan', (object)[
        'profileid' => $profileid,
        'categoryid' => $data->categoryid,
        'userid' => $USER->id,
        'status' => 'queued',
        'timecreated' => time(),
    ]);
    $task = new \tool_coursecompliance\task\run_scan();
    $task->set_custom_data(['scanid' => $scanid]);
    $task->set_userid($USER->id);
    \core\task\manager::queue_adhoc_task($task);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scanid]),
        get_string('scanqueued', 'tool_coursecompliance')
    );
}

$latestsql = "SELECT s.*, p.name AS profilename, cc.name AS categoryname
                FROM {tool_coursecompliance_scan} s
           LEFT JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
           LEFT JOIN {course_categories} cc ON cc.id = s.categoryid
            ORDER BY s.timecreated DESC";
$scans = $DB->get_records_sql($latestsql, [], 0, 20);
$latestcomplete = null;
foreach ($scans as $candidate) {
    if ($candidate->status === 'complete') {
        $latestcomplete = $candidate;
        break;
    }
}

$trend = array_values($DB->get_records_select(
    'tool_coursecompliance_scan',
    "status = :status",
    ['status' => 'complete'],
    'timecreated DESC',
    'id,timecreated,critical,warnings',
    0,
    10
));
$trend = array_reverse($trend);

$queuedcount = $DB->count_records_select(
    'tool_coursecompliance_scan',
    "status = :queued OR status = :running",
    ['queued' => 'queued', 'running' => 'running']
);

$enabledprofiles = $DB->count_records('tool_coursecompliance_profile', ['enabled' => 1]);


echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('dashboard');
echo $OUTPUT->heading(get_string('dashboard', 'tool_coursecompliance'));
echo \tool_coursecompliance\ui::intro(get_string('dashboardintro', 'tool_coursecompliance'));

$metrics = [
    [
        'label' => get_string('enabledstandards', 'tool_coursecompliance'),
        'value' => $enabledprofiles,
    ],
    [
        'label' => get_string('backgroundqueue', 'tool_coursecompliance'),
        'value' => $queuedcount,
    ],
];
if ($latestcomplete) {
    $metrics[] = [
        'label' => get_string('latestscancourses', 'tool_coursecompliance'),
        'value' => (int)$latestcomplete->courses,
    ];
    $metrics[] = [
        'label' => get_string('latestscanproblems', 'tool_coursecompliance'),
        'value' => (int)$latestcomplete->critical + (int)$latestcomplete->warnings + (int)$latestcomplete->notices,
    ];
}
echo \tool_coursecompliance\ui::metric_cards($metrics);

$trendchart = \tool_coursecompliance\report_charts::scan_trend($trend);
if ($trendchart) {
    echo html_writer::start_div('card shadow-sm mb-4');
    echo html_writer::start_div('card-body');
    echo $OUTPUT->render($trendchart);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::start_div('card shadow-sm mb-4');
echo html_writer::div(
    html_writer::tag('h3', get_string('startnewscan', 'tool_coursecompliance'), ['class' => 'h5 mb-0']),
    'card-header'
);
echo html_writer::start_div('card-body');
echo html_writer::div(get_string('startnewscanhelp', 'tool_coursecompliance'), 'text-muted mb-3');
$form->display();
echo html_writer::end_div();
echo html_writer::end_div();

if ($scans) {
    echo $OUTPUT->heading(get_string('recentscans', 'tool_coursecompliance'), 3);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm';
    $table->head = [
        get_string('date'),
        get_string('profile', 'tool_coursecompliance'),
        get_string('category'),
        get_string('status', 'tool_coursecompliance'),
        get_string('courses', 'tool_coursecompliance'),
        get_string('critical', 'tool_coursecompliance'),
        get_string('warnings', 'tool_coursecompliance'),
        get_string('actions'),
    ];
    foreach ($scans as $scan) {
        $status = \tool_coursecompliance\ui::status_badge((string)$scan->status);
        $table->data[] = [
            userdate((int)$scan->timecreated),
            $scan->profilename ? format_string($scan->profilename) : '—',
            $scan->categoryname ? format_string($scan->categoryname) : '—',
            $status,
            (int)$scan->courses,
            (int)$scan->critical,
            (int)$scan->warnings,
            html_writer::link(
                new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scan->id]),
                get_string('viewreport', 'tool_coursecompliance')
            ),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('noscansyet', 'tool_coursecompliance'), 'info');
}
echo $OUTPUT->footer();
