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
 * Safe activity rollback handler.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();
$systemcontext = context_system::instance();

$sourcebatchid = required_param('batchid', PARAM_INT);
$source = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $sourcebatchid], '*', MUST_EXIST);
\tool_coursecompliance\local\batch_access::require_manage($source);
if ((int)$source->userid !== (int)$USER->id && !is_siteadmin()) {
    require_capability('tool/coursecompliance:manageactivities', $systemcontext);
}

try {
    $batchid = (new \tool_coursecompliance\activity_batch_builder())->create_rollback($sourcebatchid, $USER->id);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]),
        get_string('rollbackpreviewcreated', 'tool_coursecompliance')
    );
} catch (\Throwable $e) {
    redirect(
        new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $sourcebatchid]),
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
