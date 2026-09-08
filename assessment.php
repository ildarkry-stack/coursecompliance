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
 * Assessment audit report page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:view', $context);

$scanid = required_param('scanid', PARAM_INT);
$type = optional_param('assessmenttype', '', PARAM_ALPHANUMEXT);
$scan = $DB->get_record('tool_cc_scan', ['id' => $scanid, 'status' => 'complete'], '*', MUST_EXIST);
$profile = $DB->get_record('tool_cc_profile', ['id' => $scan->profileid], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/assessment.php', ['scanid' => $scanid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('assessmentauditor', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('assessmentauditor', 'tool_coursecompliance'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assessmentauditor', 'tool_coursecompliance') . ' — ' . format_string($profile->name));

$baseurl = new moodle_url('/admin/tool/coursecompliance/assessment.php', ['scanid' => $scanid]);
$links = [html_writer::link($baseurl, get_string('all', 'tool_coursecompliance'), ['class' => 'btn btn-secondary me-2'])];
foreach (['exam', 'resit1', 'resit2'] as $period) {
    $links[] = html_writer::link(
        new moodle_url($baseurl, ['assessmenttype' => $period]),
        get_string('period_' . $period, 'tool_coursecompliance'),
        ['class' => 'btn btn-outline-secondary me-2']
    );
}
echo html_writer::div(implode(' ', $links), 'mb-3');

$params = ['scanid' => $scanid];
$where = 'a.scanid = :scanid';
if (in_array($type, ['exam', 'resit1', 'resit2'], true)) {
    $where .= ' AND a.assessmenttype = :assessmenttype';
    $params['assessmenttype'] = $type;
}
$sql = "SELECT a.*, c.fullname
          FROM {tool_cc_assessment} a
          JOIN {course} c ON c.id = a.courseid
         WHERE $where
      ORDER BY a.critical DESC, a.warnings DESC, a.score ASC, c.fullname";
$states = $DB->get_records_sql($sql, $params, 0, 5000);

$table = new html_table();
$table->head = [
    get_string('course'),
    get_string('assessmentperiod', 'tool_coursecompliance'),
    get_string('assessment', 'tool_coursecompliance'),
    get_string('readinessscore', 'tool_coursecompliance'),
    get_string('critical', 'tool_coursecompliance'),
    get_string('warnings', 'tool_coursecompliance'),
    get_string('notices', 'tool_coursecompliance'),
    get_string('ignored', 'tool_coursecompliance'),
    get_string('status', 'tool_coursecompliance'),
];
foreach ($states as $state) {
    $status = (int)$state->critical > 0
        ? get_string('severity_critical', 'tool_coursecompliance')
        : ((int)$state->warnings > 0
            ? get_string('severity_warning', 'tool_coursecompliance')
            : get_string('ready', 'tool_coursecompliance'));
    $objectname = empty($state->existsflag) ? get_string('missing', 'tool_coursecompliance') : format_string($state->objectname);
    $table->data[] = [
        html_writer::link(new moodle_url('/course/view.php', ['id' => $state->courseid]), format_string($state->fullname)),
        get_string('period_' . $state->assessmenttype, 'tool_coursecompliance'),
        $objectname,
        (int)$state->score . '%',
        (int)$state->critical,
        (int)$state->warnings,
        (int)$state->notices,
        (int)$state->ignored,
        $status,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
