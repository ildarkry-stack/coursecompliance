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
 * Compliance exception editor page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:manageprofiles', $context);

$resultid = required_param('resultid', PARAM_INT);
$result = $DB->get_record('tool_cc_result', ['id' => $resultid], '*', MUST_EXIST);
$scan = $DB->get_record('tool_cc_scan', ['id' => $result->scanid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $result->courseid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/exception_add.php', ['resultid' => $resultid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('createexception', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('createexception', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\exception_form();
$form->set_data((object)['resultid' => $resultid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scan->id]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $now = time();
    $record = (object)[
        'profileid' => $scan->profileid,
        'courseid' => $result->courseid,
        'rulecode' => $result->rulecode,
        'component' => $result->component,
        'objectid' => $result->objectid,
        'reason' => trim($data->reason),
        'expiresat' => empty($data->expiresat) ? 0 : (int)$data->expiresat,
        'enabled' => 1,
        'userid' => $USER->id,
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    $DB->insert_record('tool_cc_exception', $record);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/report.php', ['scanid' => $scan->id]),
        get_string('exceptioncreated', 'tool_coursecompliance')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('createexception', 'tool_coursecompliance'));
echo html_writer::div(
    format_string($course->fullname) . '<br>' . s($result->rulecode) . '<br>' . s($result->message),
    'alert alert-warning'
);
$form->display();
echo $OUTPUT->footer();
