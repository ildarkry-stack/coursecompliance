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
 * Prepare safe fixes for selected Action Center issues.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();
$context = context_system::instance();
require_capability('tool/coursecompliance:manageactivities', $context);

$resultids = optional_param_array('resultids', [], PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
if (!$returnurl) {
    $returnurl = (new moodle_url('/admin/tool/coursecompliance/action_center.php'))->out(false);
}

try {
    $batchid = (new \tool_coursecompliance\autofix_manager())->prepare_results($resultids, $USER->id);
    redirect(
        new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]),
        get_string('autofixpreviewcreated', 'tool_coursecompliance')
    );
} catch (\Throwable $e) {
    redirect($returnurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
