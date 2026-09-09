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
 * Activity batch confirmation handler.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();
$systemcontext = context_system::instance();

$batchid = required_param('batchid', PARAM_INT);
$batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid], '*', MUST_EXIST);
\tool_coursecompliance\local\batch_access::require_manage($batch);
if ($batch->status !== 'preview') {
    throw new moodle_exception('batchnotpreview', 'tool_coursecompliance');
}
if ((int)$batch->userid !== (int)$USER->id && !is_siteadmin()) {
    throw new moodle_exception('batchowneronly', 'tool_coursecompliance');
}

$transaction = $DB->start_delegated_transaction();
$batch->status = 'queued';
$DB->update_record('tool_coursecompliance_act_batch', $batch);
$task = new \tool_coursecompliance\task\apply_activity_batch();
$task->set_custom_data(['batchid' => $batchid]);
$task->set_userid($USER->id);
\core\task\manager::queue_adhoc_task($task);
$transaction->allow_commit();

redirect(
    new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]),
    get_string('batchqueued', 'tool_coursecompliance')
);
