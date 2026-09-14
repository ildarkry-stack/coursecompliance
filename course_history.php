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
 * Compliance history for one course.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:view', $systemcontext);
$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);

$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/course_history.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursehistory', 'tool_coursecompliance'));
$PAGE->set_heading(format_string($course->fullname));

$scanrows = $DB->get_records_sql(
    "SELECT s.id, s.timecreated, s.status AS scanstatus, s.scantype,
            p.name AS profilename,
            cs.critical, cs.warnings, cs.notices, cs.score, cs.status
       FROM {tool_coursecompliance_course} cs
       JOIN {tool_coursecompliance_scan} s ON s.id = cs.scanid
       JOIN {tool_coursecompliance_profile} p ON p.id = s.profileid
      WHERE cs.courseid = :courseid
   ORDER BY s.timecreated DESC",
    ['courseid' => $courseid],
    0,
    50
);
$changes = $DB->get_records_sql(
    "SELECT ch.*, b.action, b.status AS batchstatus, b.timecreated AS batchcreated, b.userid
       FROM {tool_coursecompliance_act_change} ch
       JOIN {tool_coursecompliance_act_batch} b ON b.id = ch.batchid
      WHERE ch.courseid = :courseid
   ORDER BY ch.timecreated DESC, ch.id DESC",
    ['courseid' => $courseid],
    0,
    100
);

echo $OUTPUT->header();
$actions = html_writer::link(
    new moodle_url('/admin/tool/coursecompliance/course_issues.php', ['courseid' => $courseid]),
    get_string('courseissues', 'tool_coursecompliance'),
    ['class' => 'btn btn-outline-primary me-2']
);
$actions .= html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('backtocourse', 'tool_coursecompliance'),
    ['class' => 'btn btn-outline-secondary']
);
echo \tool_coursecompliance\ui::page_heading(
    get_string('coursehistory', 'tool_coursecompliance'),
    get_string('coursehistoryintro', 'tool_coursecompliance'),
    $actions
);

if ($scanrows) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm align-middle';
    $table->head = [
        get_string('date'),
        get_string('profile', 'tool_coursecompliance'),
        get_string('scantype', 'tool_coursecompliance'),
        get_string('status'),
        get_string('readinessscore', 'tool_coursecompliance'),
        get_string('critical', 'tool_coursecompliance'),
        get_string('warnings', 'tool_coursecompliance'),
        get_string('actions'),
    ];
    foreach ($scanrows as $row) {
        $table->data[] = [
            userdate((int)$row->timecreated),
            format_string($row->profilename),
            get_string('scantype_' . ($row->scantype ?: 'audit'), 'tool_coursecompliance'),
            \tool_coursecompliance\ui::readiness_badge((string)$row->status),
            (int)$row->score . '%',
            (int)$row->critical,
            (int)$row->warnings,
            html_writer::link(
                new moodle_url('/admin/tool/coursecompliance/report.php', [
                    'scanid' => $row->id,
                    'courseid' => $courseid,
                ]),
                get_string('viewreport', 'tool_coursecompliance')
            ),
        ];
    }
    echo \tool_coursecompliance\ui::panel(
        get_string('audittrail', 'tool_coursecompliance'),
        html_writer::div(html_writer::table($table), 'table-responsive')
    );
} else {
    echo $OUTPUT->notification(get_string('coursehistoryempty', 'tool_coursecompliance'), 'info');
}

if ($changes) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-sm align-middle';
    $table->head = [
        get_string('date'),
        get_string('operation', 'tool_coursecompliance'),
        get_string('activity'),
        get_string('setting', 'tool_coursecompliance'),
        get_string('before', 'tool_coursecompliance'),
        get_string('after', 'tool_coursecompliance'),
        get_string('status'),
    ];
    foreach ($changes as $change) {
        $table->data[] = [
            userdate((int)$change->batchcreated),
            s((string)$change->action),
            s((string)$change->modname) . ' #' . (int)$change->instanceid,
            s((string)$change->fieldname),
            s((string)$change->oldvalue),
            s((string)$change->newvalue),
            s((string)$change->status),
        ];
    }
    echo \tool_coursecompliance\ui::panel(
        get_string('changehistory', 'tool_coursecompliance'),
        html_writer::div(html_writer::table($table), 'table-responsive'),
        get_string('changehistoryhelp', 'tool_coursecompliance')
    );
}

echo $OUTPUT->footer();
