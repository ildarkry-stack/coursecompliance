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
 * Blueprint import and preview page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:manageprofiles', $context);

$profileid = required_param('profileid', PARAM_INT);
$query = optional_param('q', '', PARAM_TEXT);
$importcourseid = optional_param('importcourseid', 0, PARAM_INT);
$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $profileid], '*', MUST_EXIST);
$config = \tool_coursecompliance\profile_config::decode($profile->configjson);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/blueprint.php', ['profileid' => $profileid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('blueprint', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('blueprint', 'tool_coursecompliance'));

if ($importcourseid > 0) {
    require_sesskey();
    $blueprint = \tool_coursecompliance\blueprint_manager::extract($importcourseid);
    $config['blueprint'] = $blueprint;
    $config['templatecourseid'] = $importcourseid;
    $config['blueprintversion'] = ((int)($config['blueprintversion'] ?? 0)) + 1;
    if (($config['blueprintmode'] ?? 'manual') === 'manual') {
        $config['blueprintmode'] = 'hybrid';
    }
    $profile->configjson = \tool_coursecompliance\profile_config::encode($config);
    $profile->timemodified = time();
    $DB->update_record('tool_coursecompliance_profile', $profile);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/blueprint.php', ['profileid' => $profileid]),
        get_string('blueprintimported', 'tool_coursecompliance')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('blueprintforprofile', 'tool_coursecompliance', format_string($profile->name)));
echo html_writer::div(get_string('blueprintintro', 'tool_coursecompliance'), 'alert alert-info');
echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', ['profileid' => $profileid]),
        get_string('configureblueprintrules', 'tool_coursecompliance'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

$blueprint = $config['blueprint'] ?? [];
if ($blueprint) {
    echo $OUTPUT->heading(get_string('currentblueprint', 'tool_coursecompliance'), 3);
    $meta = new html_table();
    $meta->data = [
        [get_string('sourcecourse', 'tool_coursecompliance'), s((string)($blueprint['sourcefullname'] ?? ''))],
        [get_string('blueprintversion', 'tool_coursecompliance'), (int)($config['blueprintversion'] ?? 1)],
        [
            get_string('capturedat', 'tool_coursecompliance'),
            !empty($blueprint['capturedat']) ? userdate((int)$blueprint['capturedat']) : '—',
        ],
        [get_string('blueprintactivities', 'tool_coursecompliance'), count($blueprint['activities'] ?? [])],
        [get_string('blueprintsections', 'tool_coursecompliance'), count($blueprint['sections'] ?? [])],
        [get_string('blueprintformulas', 'tool_coursecompliance'), count($blueprint['gradeformulas'] ?? [])],
    ];
    echo html_writer::table($meta);

    if (!empty($blueprint['activities'])) {
        $table = new html_table();
        $table->head = [
            get_string('idnumber'),
            get_string('activitytype', 'tool_coursecompliance'),
            get_string('activity', 'tool_coursecompliance'),
            get_string('restrictions', 'tool_coursecompliance'),
            get_string('completionconditions', 'tool_coursecompliance'),
        ];
        foreach ($blueprint['activities'] as $idnumber => $activity) {
            $table->data[] = [
                s($idnumber),
                s((string)$activity['modname']),
                format_string((string)$activity['name']),
                empty($activity['availability'])
                    ? get_string('none', 'tool_coursecompliance')
                    : s((string)$activity['availability']),
                s(json_encode($activity['completion'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->heading(get_string('selecttemplatecourse', 'tool_coursecompliance'), 3);
$searchurl = new moodle_url('/admin/tool/coursecompliance/blueprint.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $searchurl->out(false), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'profileid', 'value' => $profileid]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'q',
    'value' => $query,
    'class' => 'form-control d-inline-block me-2',
    'style' => 'max-width: 520px',
    'placeholder' => get_string('searchcourseplaceholder', 'tool_coursecompliance'),
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('search'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

if ($query !== '') {
    $like = '%' . $DB->sql_like_escape($query) . '%';
    $sql = "SELECT id, fullname, shortname, idnumber
              FROM {course}
             WHERE id <> :siteid
               AND (" . $DB->sql_like('fullname', ':fullname', false) . ' OR ' .
                    $DB->sql_like('shortname', ':shortname', false) . ' OR ' .
                    $DB->sql_like('idnumber', ':idnumber', false) . ")
          ORDER BY fullname";
    $courses = $DB->get_records_sql($sql, [
        'siteid' => SITEID,
        'fullname' => $like,
        'shortname' => $like,
        'idnumber' => $like,
    ], 0, 50);
    if (!$courses) {
        echo html_writer::div(get_string('nocoursematches', 'tool_coursecompliance'), 'alert alert-warning');
    } else {
        $table = new html_table();
        $table->head = [
            get_string('course'),
            get_string('courseshortname', 'tool_coursecompliance'),
            get_string('idnumber'),
            get_string('actions'),
        ];
        foreach ($courses as $course) {
            $button = $OUTPUT->single_button(
                new moodle_url('/admin/tool/coursecompliance/blueprint.php', [
                    'profileid' => $profileid,
                    'importcourseid' => $course->id,
                    'sesskey' => sesskey(),
                ]),
                get_string('useastemplate', 'tool_coursecompliance'),
                'post'
            );
            $table->data[] = [
                format_string($course->fullname),
                s($course->shortname),
                s($course->idnumber),
                $button,
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
