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
 * Activity operation preview builder.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:manageactivities', $systemcontext);

$selected = optional_param_array('selected', [], PARAM_INT);
$operation = required_param('operation', PARAM_ALPHAEXT);
$categoryid = required_param('categoryid', PARAM_INT);
$filters = [
    'categoryid' => $categoryid,
    'courseid' => optional_param('courseid', 0, PARAM_INT),
    'modname' => optional_param('modname', 'all', PARAM_ALPHA),
    'sectionnum' => optional_param('sectionnum', -1, PARAM_INT),
    'search' => optional_param('search', '', PARAM_TEXT),
    'datestate' => optional_param('datestate', 'all', PARAM_ALPHA),
    'visibility' => optional_param('visibility', 'all', PARAM_ALPHA),
    'restrictions' => optional_param('restrictions', 'all', PARAM_ALPHA),
    'perpage' => optional_param('perpage', 50, PARAM_INT),
];

$options = [
    'shiftamount' => optional_param('shiftamount', 1, PARAM_INT),
    'shiftunit' => optional_param('shiftunit', 'day', PARAM_ALPHA),
    'visibilitystate' => optional_param('visibilitystate', 'show', PARAM_ALPHA),
    'findtext' => optional_param('findtext', '', PARAM_TEXT),
    'replacetext' => optional_param('replacetext', '', PARAM_TEXT),
];

if ($operation === 'save_dates') {
    $posteddatevalues = optional_param_array('dates', [], PARAM_RAW_TRIMMED);
    foreach ($selected as $cmid) {
        foreach (['timeopen', 'timeclose', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'] as $field) {
            $flatkey = $cmid . '_' . $field;
            if (array_key_exists($flatkey, $posteddatevalues)) {
                $options['date_' . $cmid . '_' . $field] = $posteddatevalues[$flatkey];
            }
        }
    }
}

try {
    $batchid = (new \tool_coursecompliance\activity_batch_builder())->create(
        $operation,
        $selected,
        $options,
        $categoryid,
        $filters,
        $USER->id
    );
    redirect(new moodle_url('/admin/tool/coursecompliance/activity_batch.php', ['batchid' => $batchid]));
} catch (\Throwable $e) {
    redirect(
        new moodle_url('/admin/tool/coursecompliance/activities.php', $filters),
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
