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
 * Compliance exceptions management page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:manageprofiles', $context);

$delete = optional_param('delete', 0, PARAM_INT);
if ($delete) {
    require_sesskey();
    $DB->delete_records('tool_coursecompliance_exception', ['id' => $delete]);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/exceptions.php'),
        get_string('exceptiondeleted', 'tool_coursecompliance')
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/exceptions.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('exceptions', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('exceptions', 'tool_coursecompliance'));

$sql = "SELECT e.*, p.name AS profilename, c.fullname
          FROM {tool_coursecompliance_exception} e
          JOIN {tool_coursecompliance_profile} p ON p.id = e.profileid
          JOIN {course} c ON c.id = e.courseid
      ORDER BY e.enabled DESC, e.timecreated DESC";
$exceptions = $DB->get_records_sql($sql);
$activecount = 0;
foreach ($exceptions as $exception) {
    if (!empty($exception->enabled) && (empty($exception->expiresat) || $exception->expiresat >= time())) {
        $activecount++;
    }
}

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('standards');
echo \tool_coursecompliance\ui::standards_navigation('exceptions');
echo $OUTPUT->heading(get_string('exceptions', 'tool_coursecompliance'));
echo \tool_coursecompliance\ui::intro(get_string('exceptionsintro', 'tool_coursecompliance'));
echo \tool_coursecompliance\ui::metric_cards([
    [
        'label' => get_string('activeexceptions', 'tool_coursecompliance'),
        'value' => $activecount,
    ],
    [
        'label' => get_string('exceptions_total', 'tool_coursecompliance'),
        'value' => count($exceptions),
    ],
]);

if (!$exceptions) {
    echo $OUTPUT->notification(get_string('noexceptions', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    get_string('profile', 'tool_coursecompliance'),
    get_string('course'),
    get_string('rule', 'tool_coursecompliance'),
    get_string('exceptionreason', 'tool_coursecompliance'),
    get_string('exceptionexpires', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($exceptions as $exception) {
    $expires = empty($exception->expiresat) ? get_string('never') : userdate($exception->expiresat);
    $deleteurl = new moodle_url('/admin/tool/coursecompliance/exceptions.php', [
        'delete' => $exception->id,
        'sesskey' => sesskey(),
    ]);
    $table->data[] = [
        format_string($exception->profilename),
        html_writer::link(
            new moodle_url('/course/view.php', ['id' => $exception->courseid]),
            format_string($exception->fullname)
        ),
        s($exception->rulecode),
        s($exception->reason),
        $expires,
        html_writer::link($deleteurl, get_string('delete'), ['class' => 'text-danger']),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
