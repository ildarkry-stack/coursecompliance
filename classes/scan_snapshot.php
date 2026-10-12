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
 * Immutable audit configuration.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Immutable profile configuration associated with an audit.
 */
final class scan_snapshot {
    /**
     * Resolve the historical configuration, with an explicit legacy fallback.
     *
     * @param \stdClass $scan Audit.
     * @return array Profile configuration.
     */
    public static function config(\stdClass $scan): array {
        global $DB;
        if (!empty($scan->configsnapshot)) {
            return profile_config::decode($scan->configsnapshot);
        }
        $profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid]);
        return $profile ? profile_config::decode($profile->configjson) : [];
    }

    /**
     * Capture a profile once before tasks are published.
     *
     * @param \stdClass $scan Audit, updated in place.
     */
    public static function capture(\stdClass $scan): void {
        global $DB;
        if (!empty($scan->configsnapshot)) {
            return;
        }
        $profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
        $scan->configsnapshot = profile_config::encode(profile_config::decode($profile->configjson));
        $scan->confighash = hash('sha256', $scan->configsnapshot);
        $DB->update_record('tool_coursecompliance_scan', (object)[
            'id' => $scan->id,
            'configsnapshot' => $scan->configsnapshot,
            'confighash' => $scan->confighash,
        ]);
    }
}
