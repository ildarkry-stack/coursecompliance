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
 * Compliance profiles management page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:manageprofiles', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/profiles.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('profiles', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('profiles', 'tool_coursecompliance'));

$profiles = $DB->get_records('tool_coursecompliance_profile', null, 'enabled DESC, name ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('profiles', 'tool_coursecompliance'));
echo html_writer::div(get_string('profilesintro', 'tool_coursecompliance'), 'alert alert-info');
echo html_writer::link(
    new moodle_url('/admin/tool/coursecompliance/profile_edit.php'),
    get_string('addprofile', 'tool_coursecompliance'),
    ['class' => 'btn btn-primary mb-3']
);

$table = new html_table();
$table->head = [
    get_string('profilename', 'tool_coursecompliance'),
    get_string('status', 'tool_coursecompliance'),
    get_string('profiletargets', 'tool_coursecompliance'),
    get_string('actions'),
];
foreach ($profiles as $profile) {
    $config = \tool_coursecompliance\profile_config::decode($profile->configjson);
    $targets = [];
    foreach (['exam', 'resit1', 'resit2'] as $type) {
        $requiredkey = $type === 'exam' ? 'requireexam' : 'require' . $type;
        if (!empty($config[$requiredkey])) {
            $targets[] = get_string('period_' . $type, 'tool_coursecompliance') . ': ' . s($config[$type . 'idnumber']);
        }
    }
    if (!$targets) {
        $targets[] = get_string('courseonlyprofile', 'tool_coursecompliance');
    }
    if (!empty($config['blueprint']) && is_array($config['blueprint'])) {
        $targets[] = get_string('blueprintsourcebrief', 'tool_coursecompliance', (object)[
            'course' => $config['blueprint']['sourcefullname'] ?? '',
            'version' => (int)($config['blueprintversion'] ?? 1),
        ]);
    }
    $actions = html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/profile_edit.php', ['id' => $profile->id]),
        get_string('edit')
    );
    $actions .= ' · ' . html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/profile_edit.php', ['copy' => $profile->id]),
        get_string('duplicateprofile', 'tool_coursecompliance')
    );
    $actions .= ' · ' . html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/blueprint.php', ['profileid' => $profile->id]),
        get_string('blueprint', 'tool_coursecompliance')
    );
    $actions .= ' · ' . html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', ['profileid' => $profile->id]),
        get_string('blueprintrules', 'tool_coursecompliance')
    );
    $table->data[] = [
        format_string($profile->name),
        $profile->enabled ? get_string('enabled', 'tool_coursecompliance') : get_string('disabled', 'tool_coursecompliance'),
        implode('<br>', $targets),
        $actions,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
