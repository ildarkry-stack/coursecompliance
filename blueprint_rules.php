<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Visual Blueprint rule constructor page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('tool/coursecompliance:manageprofiles', $context);

$profileid = required_param('profileid', PARAM_INT);
$profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $profileid], '*', MUST_EXIST);
$config = \tool_coursecompliance\profile_config::decode($profile->configjson);
$rules = $config['blueprintrules'];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', ['profileid' => $profileid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('blueprintrules', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('blueprintrules', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\blueprint_rules_form(null, [
    'profileid' => $profileid,
    'config' => $config,
]);
$form->set_data(\tool_coursecompliance\blueprint_rule_mapper::to_form_data($rules, $profileid));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/coursecompliance/blueprint.php', ['profileid' => $profileid]));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $config['blueprintrules'] = \tool_coursecompliance\blueprint_rule_mapper::from_form_data($data, $rules);
    $profile->configjson = \tool_coursecompliance\profile_config::encode($config);
    $profile->timemodified = time();
    $DB->update_record('tool_coursecompliance_profile', $profile);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/blueprint_rules.php', ['profileid' => $profileid]),
        get_string('blueprintrulessaved', 'tool_coursecompliance')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('blueprintrulesforprofile', 'tool_coursecompliance', format_string($profile->name)));
echo html_writer::div(get_string('blueprintrulesintro', 'tool_coursecompliance'), 'alert alert-info');

if (empty($config['blueprint'])) {
    echo html_writer::div(get_string('blueprintrulesnotemplate', 'tool_coursecompliance'), 'alert alert-warning');
} else {
    $blueprint = $config['blueprint'];
    if ((int)($blueprint['schema'] ?? 1) < 2) {
        echo html_writer::div(get_string('blueprintlegacywarning', 'tool_coursecompliance'), 'alert alert-warning');
    }
    $sourceinfo = (object)[
        'course' => $blueprint['sourcefullname'] ?? '',
        'version' => (int)($config['blueprintversion'] ?? 1),
    ];
    echo html_writer::div(
        get_string('blueprintsourcebrief', 'tool_coursecompliance', $sourceinfo),
        'alert alert-secondary'
    );
}

$form->display();
echo $OUTPUT->footer();
