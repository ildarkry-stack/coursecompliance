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
 * Plugin settings inside the Course Compliance interface.
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
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/plugin_settings.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('nav_settings', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('nav_settings', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\plugin_settings_form();
if ($data = $form->get_data()) {
    set_config('retentiondays', max(1, (int)$data->retentiondays), 'tool_coursecompliance');
    set_config('scanbatchsize', max(1, min(50, (int)$data->scanbatchsize)), 'tool_coursecompliance');
    set_config('sessiondefaultcategory', (int)$data->sessiondefaultcategory, 'tool_coursecompliance');
    redirect(
        $PAGE->url,
        get_string('settingssaved', 'tool_coursecompliance'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$form->set_data((object)[
    'retentiondays' => (int)get_config('tool_coursecompliance', 'retentiondays') ?: 90,
    'scanbatchsize' => (int)get_config('tool_coursecompliance', 'scanbatchsize') ?: 10,
    'sessiondefaultcategory' => (int)get_config('tool_coursecompliance', 'sessiondefaultcategory'),
]);

echo $OUTPUT->header();
echo \tool_coursecompliance\ui::primary_navigation('settings');
echo \tool_coursecompliance\ui::page_heading(
    get_string('nav_settings', 'tool_coursecompliance'),
    get_string('settingsintro', 'tool_coursecompliance')
);
$form->display();
echo $OUTPUT->footer();
