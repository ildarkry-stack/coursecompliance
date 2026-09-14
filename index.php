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
        'courselimit' => (int)$data->courselimit,
        'userid' => $USER->id,
        'status' => 'queued',
        'timecreated' => time(),
    ]);
    $task = new \tool_coursecompliance\task\run_scan();
    $task->set_custom_data([
        'scanid' => $scanid,
        'courselimit' => (int)$data->courselimit,
    ]);
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
$fullscansql = "SELECT s.*, p.name AS profilename, cc.name AS categoryname
                  FROM {tool_coursecompliance_scan} s
             LEFT JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
             LEFT JOIN {course_categories} cc ON cc.id = s.categoryid
                 WHERE s.status = :status AND s.courselimit = 0
              ORDER BY s.timecreated DESC";
$fullscans = $DB->get_records_sql($fullscansql, ['status' => 'complete'], 0, 1);
if ($fullscans) {
    $latestcomplete = reset($fullscans);
}
if (!$latestcomplete) {
    foreach ($scans as $candidate) {
        if ($candidate->status === 'complete') {
            $latestcomplete = $candidate;
            break;
        }
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

$readycourses = 0;
$notreadycourses = 0;
$readinesspercent = 0;
if ($latestcomplete) {
    $notreadycourses = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT courseid)
           FROM {tool_coursecompliance_result}
          WHERE scanid = :scanid",
        ['scanid' => (int)$latestcomplete->id]
    );
    $notreadycourses = min((int)$latestcomplete->courses, $notreadycourses);
    $readycourses = max(0, (int)$latestcomplete->courses - $notreadycourses);
    if ((int)$latestcomplete->courses > 0) {
        $readinesspercent = (int)round(($readycourses / (int)$latestcomplete->courses) * 100);
    }
}

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('dashboard');
echo \tool_coursecompliance\ui::page_heading(
    get_string('dashboard', 'tool_coursecompliance'),
    get_string('dashboardintro', 'tool_coursecompliance')
);

$metrics = [
    [
        'label' => get_string('enabledstandards', 'tool_coursecompliance'),
        'value' => $enabledprofiles,
        'tone' => 'info',
    ],
    [
        'label' => get_string('backgroundqueue', 'tool_coursecompliance'),
        'value' => $queuedcount,
        'tone' => $queuedcount > 0 ? 'warning' : 'secondary',
    ],
];
if ($latestcomplete) {
    $metrics[] = [
        'label' => get_string('readycourses', 'tool_coursecompliance'),
        'value' => $readycourses,
        'help' => get_string('latestscancourseshelp', 'tool_coursecompliance', (int)$latestcomplete->courses),
        'tone' => 'success',
        'url' => new moodle_url('/admin/tool/coursecompliance/report.php', [
            'scanid' => (int)$latestcomplete->id,
            'kind' => 'ready',
        ]),
    ];
    $metrics[] = [
        'label' => get_string('notreadycourses', 'tool_coursecompliance'),
        'value' => $notreadycourses,
        'help' => get_string(
            'latestscanproblemshelp',
            'tool_coursecompliance',
            (int)$latestcomplete->critical + (int)$latestcomplete->warnings + (int)$latestcomplete->notices
        ),
        'tone' => $notreadycourses > 0 ? 'danger' : 'success',
        'url' => new moodle_url('/admin/tool/coursecompliance/report.php', [
            'scanid' => (int)$latestcomplete->id,
        ]),
    ];
    $metrics[] = [
        'label' => get_string('coursereadinesspercent', 'tool_coursecompliance'),
        'value' => $readinesspercent . '%',
        'tone' => $readinesspercent === 100 ? 'success' : ($readinesspercent >= 80 ? 'warning' : 'danger'),
    ];
}
echo \tool_coursecompliance\ui::metric_cards($metrics);

if ($latestcomplete) {
    $readinesschart = \tool_coursecompliance\report_charts::course_readiness($readycourses, $notreadycourses);
    $trendchart = \tool_coursecompliance\report_charts::scan_trend($trend);

    echo html_writer::start_div('row g-3 mb-4');
    echo html_writer::start_div('col-12 col-xl-5');
    $readinessbody = '';
    if ($readinesschart) {
        $readinessbody .= $OUTPUT->render($readinesschart);
    }
    $readinessbody .= html_writer::div(
        html_writer::span('', 'cc-readiness-dot bg-success') .
        html_writer::span(get_string('readycourses', 'tool_coursecompliance'), 'flex-grow-1') .
        html_writer::tag('strong', (string)$readycourses),
        'cc-readiness-stat'
    );
    $readinessbody .= html_writer::div(
        html_writer::span('', 'cc-readiness-dot bg-danger') .
        html_writer::span(get_string('notreadycourses', 'tool_coursecompliance'), 'flex-grow-1') .
        html_writer::tag('strong', (string)$notreadycourses),
        'cc-readiness-stat'
    );
    $readinesslabel = get_string(
        'latestscanlabel',
        'tool_coursecompliance',
        userdate((int)$latestcomplete->timecreated)
    );
    if (!empty($latestcomplete->courselimit)) {
        $readinesslabel .= ' · ' . get_string(
            'scanlimitedto',
            'tool_coursecompliance',
            (int)$latestcomplete->courselimit
        );
    }
    $readinessbody .= html_writer::div($readinesslabel, 'small text-muted mt-2');
    echo \tool_coursecompliance\ui::panel(
        get_string('coursereadinessreport', 'tool_coursecompliance'),
        $readinessbody,
        get_string('coursereadinessreporthelp', 'tool_coursecompliance')
    );
    echo html_writer::end_div();

    echo html_writer::start_div('col-12 col-xl-7');
    if ($trendchart) {
        echo \tool_coursecompliance\ui::panel(
            get_string('chart_scantrend_title', 'tool_coursecompliance'),
            $OUTPUT->render($trendchart),
            get_string('scantrendhelp', 'tool_coursecompliance')
        );
    } else {
        echo \tool_coursecompliance\ui::panel(
            get_string('chart_scantrend_title', 'tool_coursecompliance'),
            html_writer::div(get_string('scantrendnotenoughdata', 'tool_coursecompliance'), 'cc-empty-state')
        );
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
}

ob_start();
echo html_writer::div(get_string('startnewscanhelp', 'tool_coursecompliance'), 'text-muted mb-3');
$form->display();
$scanformhtml = ob_get_clean();
echo \tool_coursecompliance\ui::panel(
    get_string('startnewscan', 'tool_coursecompliance'),
    $scanformhtml
);

if ($scans) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm align-middle';
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
                get_string('viewreport', 'tool_coursecompliance'),
                ['class' => 'btn btn-sm btn-outline-primary']
            ),
        ];
    }
    echo \tool_coursecompliance\ui::panel(
        get_string('recentscans', 'tool_coursecompliance'),
        html_writer::table($table)
    );
} else {
    echo \tool_coursecompliance\ui::panel(
        get_string('recentscans', 'tool_coursecompliance'),
        html_writer::div(get_string('noscansyet', 'tool_coursecompliance'), 'cc-empty-state')
    );
}
echo $OUTPUT->footer();
