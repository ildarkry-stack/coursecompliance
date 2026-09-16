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
 * CourseCompliance runtime diagnostics.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/diagnostics.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('diagnostics', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('diagnostics', 'tool_coursecompliance'));

$data = \tool_coursecompliance\diagnostics::snapshot();
$checks = $data['checks'];
$allok = true;
foreach ($checks as $check) {
    if (!$check['ok']) {
        $allok = false;
        break;
    }
}

$lastcompleted = (int)$data['lastcompleted'] > 0
    ? userdate((int)$data['lastcompleted'], get_string('strftimedatetime', 'langconfig'))
    : get_string('never');

$metrics = [
    [
        'value' => (string)$data['pluginrelease'],
        'label' => get_string('diagnostics_pluginrelease', 'tool_coursecompliance'),
        'tone' => 'secondary',
    ],
    [
        'value' => (int)$data['pendingtasks'],
        'label' => get_string('diagnostics_pendingtasks', 'tool_coursecompliance'),
        'tone' => 'info',
    ],
    [
        'value' => (int)$data['failedscans'],
        'label' => get_string('diagnostics_failedscans', 'tool_coursecompliance'),
        'tone' => (int)$data['failedscans'] > 0 ? 'danger' : 'success',
    ],
    [
        'value' => (int)$data['stalledscans'],
        'label' => get_string('diagnostics_stalledscans', 'tool_coursecompliance'),
        'tone' => (int)$data['stalledscans'] > 0 ? 'warning' : 'success',
    ],
    [
        'value' => $lastcompleted,
        'label' => get_string('diagnostics_lastcompleted', 'tool_coursecompliance'),
        'tone' => 'secondary',
    ],
];

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('diagnostics_check', 'tool_coursecompliance'),
    get_string('diagnostics_status', 'tool_coursecompliance'),
    get_string('diagnostics_detected', 'tool_coursecompliance'),
    get_string('diagnostics_requirement', 'tool_coursecompliance'),
];

$definitions = [
    'moodle' => [
        get_string('diagnostics_moodle', 'tool_coursecompliance'),
        get_string('diagnostics_moodle_requirement', 'tool_coursecompliance'),
    ],
    'php' => [
        get_string('diagnostics_php', 'tool_coursecompliance'),
        get_string('diagnostics_php_requirement', 'tool_coursecompliance', $checks['php']['minimum']),
    ],
    'database' => [
        get_string('diagnostics_database', 'tool_coursecompliance'),
        get_string('diagnostics_database_requirement', 'tool_coursecompliance'),
    ],
    'apis' => [
        get_string('diagnostics_apis', 'tool_coursecompliance'),
        get_string('diagnostics_apis_requirement', 'tool_coursecompliance'),
    ],
];

foreach ($definitions as $key => [$label, $requirement]) {
    $check = $checks[$key];
    $status = $check['ok']
        ? html_writer::span(get_string('diagnostics_ok', 'tool_coursecompliance'), 'badge bg-success')
        : html_writer::span(get_string('diagnostics_problem', 'tool_coursecompliance'), 'badge bg-danger');
    $detected = $key === 'apis'
        ? get_string(
            $check['ok'] ? 'diagnostics_available' : 'diagnostics_missing',
            'tool_coursecompliance'
        )
        : (string)$check['value'];
    $table->data[] = [
        s($label),
        $status,
        s($detected),
        s($requirement),
    ];
}

$taskdetail = '';
if ((int)$data['oldesttask'] > 0) {
    $taskdetail = get_string(
        'diagnostics_oldesttask',
        'tool_coursecompliance',
        userdate((int)$data['oldesttask'], get_string('strftimedatetime', 'langconfig'))
    );
}

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('settings');
echo \tool_coursecompliance\ui::settings_navigation('diagnostics');
echo \tool_coursecompliance\ui::page_heading(
    get_string('diagnostics', 'tool_coursecompliance'),
    get_string('diagnostics_intro', 'tool_coursecompliance')
);

if ($allok) {
    echo $OUTPUT->notification(
        get_string('diagnostics_environmentok', 'tool_coursecompliance'),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    echo $OUTPUT->notification(
        get_string('diagnostics_environmentproblem', 'tool_coursecompliance'),
        \core\output\notification::NOTIFY_WARNING
    );
}

echo \tool_coursecompliance\ui::metric_cards($metrics);
echo html_writer::start_div('cc-panel');
echo html_writer::div(
    html_writer::tag('h3', get_string('diagnostics_environment', 'tool_coursecompliance'), ['class' => 'h5 mb-0']),
    'cc-panel-header'
);
echo html_writer::start_div('cc-panel-body');
echo html_writer::table($table);
if ($taskdetail !== '') {
    echo html_writer::div(s($taskdetail), 'small text-muted mt-2');
}
echo html_writer::div(get_string('diagnostics_supportnote', 'tool_coursecompliance'), 'alert alert-info mt-3 mb-0');
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
