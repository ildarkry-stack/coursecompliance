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
 * Tests for detailed report helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Detailed report helper tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursecompliance\report_helper
 */
final class report_helper_test extends \advanced_testcase {
    /**
     * Required exam and blueprint missing activity are classified as missing.
     */
    public function test_missing_results_are_classified(): void {
        $result = (object)['rulecode' => 'QUIZ_EXAM_REQUIRED', 'actual' => 'Missing'];
        $this->assertTrue(report_helper::is_missing($result));

        $result = (object)['rulecode' => 'BLUEPRINT_ACTIVITY_MISSING', 'actual' => 'Missing'];
        $this->assertTrue(report_helper::is_missing($result));

        $result = (object)['rulecode' => 'QUIZ_ATTEMPTS_STANDARD', 'actual' => '3'];
        $this->assertFalse(report_helper::is_missing($result));
    }
}
