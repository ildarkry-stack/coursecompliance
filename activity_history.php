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
 * Activity operation history page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:view', $systemcontext);

$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;
$total = $DB->count_records('tool_cc_act_batch');
$batches = $DB->get_records('tool_cc_act_batch', null, 'timecreated DESC', '*', $page * $perpage, $perpage);
$userids = array_unique(array_map(static fn ($b) => (int)$b->userid, $batches));
$users = $userids
    ? $DB->get_records_list(
        'user',
        'id',
        $userids,
        '',
        'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
    )
    : [];

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/activity_history.php', ['page' => $page]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('operationhistory', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('operationhistory', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('operationhistory', 'tool_coursecompliance'));

$table = new html_table();
$table->head = [
    get_string('date'),
    get_string('user'),
    get_string('operation', 'tool_coursecompliance'),
    get_string('sourcebatch', 'tool_coursecompliance'),
    get_string('status', 'tool_coursecompliance'),
    get_string('changes', 'tool_coursecompliance'),
    get_string('applied', 'tool_coursecompliance'),
    get_string('skipped', 'tool_coursecompliance'),
    get_string('failed', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($batches as $batch) {
    $user = $users[$batch->userid] ?? null;
    $table->data[] = [
        userdate($batch->timecreated),
        $user ? fullname($user) : '#' . $batch->userid,
        get_string('operation_' . $batch->action, 'tool_coursecompliance'),
        !empty($batch->sourcebatchid)
            ? html_writer::link(
                new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batch->sourcebatchid]),
                '#' . $batch->sourcebatchid
            )
            : '—',
        get_string('batchstatus_' . $batch->status, 'tool_coursecompliance'),
        $batch->totalchanges,
        $batch->applied,
        $batch->skipped,
        $batch->failed,
        html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batch->id]),
            get_string('view', 'tool_coursecompliance')
        ),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/admin/tool/coursecompliance/activity_history.php'));
echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/activities.php'),
        get_string('backtoactivities', 'tool_coursecompliance')
    ),
    'mt-3'
);
echo $OUTPUT->footer();
