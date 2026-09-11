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
 * Compliance profile editor page.
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

$id = optional_param('id', 0, PARAM_INT);
$copy = optional_param('copy', 0, PARAM_INT);
$profile = null;
if ($id) {
    $profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $id], '*', MUST_EXIST);
} else if ($copy) {
    $profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $copy], '*', MUST_EXIST);
    $profile->id = 0;
    $profile->name = get_string('copyofprofile', 'tool_coursecompliance', $profile->name);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/profile_edit.php', ['id' => $id, 'copy' => $copy]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('editprofile', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('editprofile', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\profile_form();
if ($profile) {
    $initial = (object)array_merge(
        \tool_coursecompliance\profile_config::decode($profile->configjson),
        [
            'id' => $profile->id,
            'name' => $profile->name,
            'description' => $profile->description,
            'enabled' => $profile->enabled,
        ]
    );
    $form->set_data($initial);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/coursecompliance/profiles.php'));
}
if ($data = $form->get_data()) {
    require_sesskey();
    $config = $profile
        ? \tool_coursecompliance\profile_config::decode($profile->configjson)
        : \tool_coursecompliance\profile_config::defaults();
    foreach (array_keys(\tool_coursecompliance\profile_config::defaults()) as $key) {
        if ($key !== 'blueprint' && property_exists($data, $key)) {
            $config[$key] = $data->{$key};
        }
    }
    $record = (object)[
        'name' => trim($data->name),
        'description' => $data->description,
        'enabled' => empty($data->enabled) ? 0 : 1,
        'configjson' => \tool_coursecompliance\profile_config::encode($config),
        'timemodified' => time(),
    ];
    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('tool_coursecompliance_profile', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('tool_coursecompliance_profile', $record);
    }
    redirect(new moodle_url('/admin/tool/coursecompliance/profiles.php'), get_string('profilesaved', 'tool_coursecompliance'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editprofile', 'tool_coursecompliance'));
$form->display();
echo $OUTPUT->footer();
