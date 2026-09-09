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
 * Classes for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Normalises compliance profile configuration.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_config {
    /**
     * Get profile defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'examidnumber' => 'exam',
            'resit1idnumber' => 'resit1',
            'resit2idnumber' => 'resit2',
            'requirecompletion' => true,
            'requireteacher' => true,
            'requireexam' => true,
            'requireresit1' => false,
            'requireresit2' => false,
            'examattempts' => 1,
            'examtimelimit' => 0,
            'examgrade' => 0,
            'requiregradepass' => false,
            'mingradepass' => 0,
            'minquestions' => 1,
            'maxquestions' => 0,
            'hideanswerswhileopen' => true,
            'hidecorrectnesswhileopen' => false,
            'requirepassword' => false,
            'requiresubnet' => false,
            'expectedbrowsersecurity' => '',
            'requireshuffleanswers' => false,
            'warnoverrides' => true,
            'maxoverrideattempts' => 0,
            'maxoverrideextension' => 0,
        ];
    }

    /**
     * Decode and merge a stored configuration JSON value.
     *
     * @param string|null $json Stored JSON.
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array {
        $decoded = json_decode($json ?: '{}', true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        return array_replace(self::defaults(), $decoded);
    }

    /**
     * Encode configuration for storage.
     *
     * @param array $config Configuration.
     * @return string
     */
    public static function encode(array $config): string {
        return json_encode(
            array_replace(self::defaults(), $config),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Create a compact default profile configuration for a profile kind.
     *
     * @param string $kind Profile kind: exam, credit or practice.
     * @return array<string, mixed>
     */
    public static function preset(string $kind): array {
        $config = self::defaults();
        if ($kind === 'credit') {
            $config['examidnumber'] = 'credit';
            $config['examattempts'] = 2;
            $config['hideanswerswhileopen'] = true;
        } else if ($kind === 'practice') {
            $config['requireexam'] = false;
            $config['requireresit1'] = false;
            $config['requireresit2'] = false;
            $config['minquestions'] = 0;
            $config['hideanswerswhileopen'] = false;
            $config['warnoverrides'] = false;
        }
        return $config;
    }
}
