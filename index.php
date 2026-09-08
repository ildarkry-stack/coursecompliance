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
require_login();
require_capability('tool/coursecompliance:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('pluginname', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\scan_form();
if ($data = $form->get_data()) {
    require_capability('tool/coursecompliance:scan', context_system::instance());
    $scanid = $DB->insert_record('tool_cc_scan', (object)[
        'profileid' => $data->profileid,
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'tool_coursecompliance'));
echo html_writer::div(get_string('intro', 'tool_coursecompliance'), 'alert alert-info');
$dashboardlinks = [];
$dashboardlinks[] = html_writer::link(
    new moodle_url('/admin/tool/coursecompliance/readiness.php'),
    get_string('sessionreadiness', 'tool_coursecompliance'),
    ['class' => 'btn btn-primary me-2']
);
$dashboardlinks[] = html_writer::link(
    new moodle_url('/admin/tool/coursecompliance/activities.php'),
    get_string('activityoperations', 'tool_coursecompliance'),
    ['class' => 'btn btn-secondary me-2']
);
$dashboardlinks[] = html_writer::link(
    new moodle_url('/admin/tool/coursecompliance/activity_history.php'),
    get_string('operationhistory', 'tool_coursecompliance'),
    ['class' => 'btn btn-secondary me-2']
);
if (has_capability('tool/coursecompliance:manageprofiles', context_system::instance())) {
    $dashboardlinks[] = html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/profiles.php'),
        get_string('profiles', 'tool_coursecompliance'),
        ['class' => 'btn btn-outline-secondary']
    );
}
echo html_writer::div(implode(' ', $dashboardlinks), 'mb-4');
$form->display();

$scans = $DB->get_records('tool_cc_scan', null, 'timecreated DESC', '*', 0, 20);
if ($scans) {
    $table = new html_table();
    $table->head = [
        get_string('date'),
        get_string('status', 'tool_coursecompliance'),
        get_string('courses', 'tool_coursecompliance'),
        get_string('assessments', 'tool_coursecompliance'),
        get_string('critical', 'tool_coursecompliance'),
        get_string('warnings', 'tool_coursecompliance'),
        get_string('actions'),
    ];
    foreach ($scans as $scan) {
        $table->data[] = [
            userdate($scan->timecreated),
            get_string('status_' . $scan->status, 'tool_coursecompliance'),
            $scan->courses,
            $scan->assessments,
            $scan->critical,
            $scan->warnings,
            html_writer::link(
                new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scan->id]),
                get_string('viewreport', 'tool_coursecompliance')
            ),
        ];
    }
    echo $OUTPUT->heading(get_string('recentscans', 'tool_coursecompliance'), 3);
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
