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
 * Native availability editor for Blueprint exam/resit rules.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:manageprofiles', $systemcontext);

$profileid = required_param('profileid', PARAM_INT);
$assessmenttype = required_param('type', PARAM_ALPHA);
if (!in_array($assessmenttype, ['exam', 'resit1', 'resit2'], true)) {
    throw new moodle_exception('invalidparameter');
}

$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $profileid], '*', MUST_EXIST);
$config = \tool_coursecompliance\profile_config::decode($profile->configjson);
$blueprint = is_array($config['blueprint'] ?? null) ? $config['blueprint'] : [];
$sourcecourseid = (int)($blueprint['sourcecourseid'] ?? 0);
if ($sourcecourseid <= 0 || !$DB->record_exists('course', ['id' => $sourcecourseid])) {
    redirect(
        new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', ['profileid' => $profileid]),
        get_string('availabilityneedstemplate', 'tool_coursecompliance'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$course = get_course($sourcecourseid);
$coursecontext = context_course::instance($course->id);
$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/availability_rules.php', [
    'profileid' => $profileid,
    'type' => $assessmenttype,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('availabilityeditor', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('availabilityeditor', 'tool_coursecompliance'));

$targetidnumber = trim((string)($config[$assessmenttype . 'idnumber'] ?? $assessmenttype));
$targetcmrecords = $DB->get_records('course_modules', [
    'course' => $course->id,
    'idnumber' => $targetidnumber,
]);
$cm = null;
if (count($targetcmrecords) === 1) {
    $targetcmrecord = reset($targetcmrecords);
    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm((int)$targetcmrecord->id);
}

$targetidnumbers = [];
foreach (['exam', 'resit1', 'resit2'] as $type) {
    $idnumber = trim((string)($config[$type . 'idnumber'] ?? $type));
    if ($idnumber !== '') {
        $targetidnumbers[] = $idnumber;
    }
}
$maps = \tool_coursecompliance\availability_blueprint::build_maps($course->id, $targetidnumbers);

$rules = $config['blueprintrules'];
$assessmentrules = $rules['assessments'][$assessmenttype] ?? [];
$portablejson = trim((string)($assessmentrules['availability_manual'] ?? ''));
if ($portablejson === '') {
    $templateactivity = $blueprint['activities'][$targetidnumber] ?? [];
    $portablejson = trim((string)($templateactivity['availability'] ?? ''));
}
$rawjson = \tool_coursecompliance\availability_blueprint::denormalise_json($portablejson, $maps);

$form = new \tool_coursecompliance\form\availability_rules_form(null, [
    'course' => $course,
    'cm' => $cm,
    'profileid' => $profileid,
    'assessmenttype' => $assessmenttype,
    'targetidnumber' => $targetidnumber,
    'allowedcmids' => array_values($maps['idnumbertocm']),
    'allowedgradeids' => array_values($maps['idnumbertogradeid']),
]);
$form->set_data((object)[
    'profileid' => $profileid,
    'assessmenttype' => $assessmenttype,
    'availabilityconditionsjson' => $rawjson,
]);

$targetlabels = [];
foreach (['exam', 'resit1', 'resit2'] as $type) {
    $idnumber = trim((string)($config[$type . 'idnumber'] ?? $type));
    if ($idnumber !== '') {
        $targetlabels[$idnumber] = get_string('period_' . $type, 'tool_coursecompliance') . ' (' . $idnumber . ')';
    }
}
$filterconfig = [
    'allowedcmids' => array_values($maps['idnumbertocm']),
    'allowedgradeids' => array_values($maps['idnumbertogradeid']),
    'cmlabels' => [],
    'gradelabels' => [],
];
foreach ($maps['idnumbertocm'] as $idnumber => $cmid) {
    $filterconfig['cmlabels'][(string)$cmid] = $targetlabels[$idnumber] ?? $idnumber;
}
foreach ($maps['idnumbertogradeid'] as $idnumber => $gradeid) {
    $filterconfig['gradelabels'][(string)$gradeid] = $targetlabels[$idnumber] ?? $idnumber;
}
$PAGE->requires->js_call_amd('tool_coursecompliance/availability_filter', 'init', [$filterconfig]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', [
        'profileid' => $profileid,
    ], $assessmenttype . 'block'));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $portablejson = \tool_coursecompliance\availability_blueprint::normalise_json(
        (string)($data->availabilityconditionsjson ?? ''),
        $maps
    );
    $config['blueprintrules']['assessments'][$assessmenttype]['availability_source'] = 'manual';
    $config['blueprintrules']['assessments'][$assessmenttype]['availability_manual'] = $portablejson;
    $profile->configjson = \tool_coursecompliance\profile_config::encode($config);
    $profile->timemodified = time();
    $DB->update_record('tool_coursecompliance_profile', $profile);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', [
            'profileid' => $profileid,
        ], $assessmenttype . 'block'),
        get_string('availabilitysaved', 'tool_coursecompliance')
    );
}

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('standards');
echo \tool_coursecompliance\ui::standards_navigation('profiles');
echo $OUTPUT->heading(get_string(
    'availabilityeditorfor',
    'tool_coursecompliance',
    strtoupper($assessmenttype)
));
echo \tool_coursecompliance\ui::intro(get_string('availabilitynativeintro', 'tool_coursecompliance'));
if (!$cm) {
    echo html_writer::div(
        get_string('availabilitytargetmissingtemplate', 'tool_coursecompliance', $targetidnumber),
        'alert alert-warning'
    );
}
$form->display();
echo $OUTPUT->footer();
