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
 * Installation steps for tool_coursecompliance.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Install tool_coursecompliance defaults.
 */
function xmldb_tool_coursecompliance_install(): void {
    global $DB;

    $now = time();
    $presets = [
        'exam' => [
            'name' => get_string('profilepreset_exam', 'tool_coursecompliance'),
            'description' => get_string('profilepreset_exam_desc', 'tool_coursecompliance'),
        ],
        'credit' => [
            'name' => get_string('profilepreset_credit', 'tool_coursecompliance'),
            'description' => get_string('profilepreset_credit_desc', 'tool_coursecompliance'),
        ],
        'practice' => [
            'name' => get_string('profilepreset_practice', 'tool_coursecompliance'),
            'description' => get_string('profilepreset_practice_desc', 'tool_coursecompliance'),
        ],
    ];
    foreach ($presets as $kind => $preset) {
        $DB->insert_record('tool_cc_profile', (object)[
            'name' => $preset['name'],
            'description' => $preset['description'],
            'enabled' => 1,
            'configjson' => \tool_coursecompliance\profile_config::encode(
                \tool_coursecompliance\profile_config::preset($kind)
            ),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    $legacycategory = (int)get_config('local_examdates', 'default_category');
    if ($legacycategory > 0) {
        set_config('sessiondefaultcategory', $legacycategory, 'tool_coursecompliance');
    }
}
