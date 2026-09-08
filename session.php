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
 * Session planning page.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:view', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/session.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('sessionplanning', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('sessionplanning', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\session_schedule_form();
if ($data = $form->get_data()) {
    require_sesskey();
    try {
        $batchid = (new \tool_coursecompliance\session_batch_builder())->create($data, $USER->id);
        redirect(new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]));
    } catch (\Throwable $e) {
        redirect(
            new moodle_url('/admin/tool/coursecompliance/session.php'),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sessionplanning', 'tool_coursecompliance'));
echo html_writer::div(get_string('sessionplanning_intro', 'tool_coursecompliance'), 'alert alert-info');
if ($DB->record_exists('config_plugins', ['plugin' => 'local_examdates'])) {
    echo html_writer::div(get_string('legacyexamdatesdetected', 'tool_coursecompliance'), 'alert alert-secondary');
}
$form->display();
echo $OUTPUT->footer();
