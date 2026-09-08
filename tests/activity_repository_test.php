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
 * Tests for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Tests for normalized activity metadata.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_coursecompliance\activity_repository
 */
final class activity_repository_test extends \advanced_testcase {
    /**
     * Supported activity types expose the expected editable date fields.
     */
    public function test_supported_date_fields(): void {
        $this->assertSame(['timeopen', 'timeclose'], activity_repository::date_fields('quiz'));
        $this->assertSame(
            ['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'],
            activity_repository::date_fields('assign')
        );
        $this->assertSame(['duedate', 'cutoffdate'], activity_repository::date_fields('forum'));
        $this->assertSame([], activity_repository::date_fields('page'));
    }

    /**
     * Visibility state helper maps Moodle flags consistently.
     */
    public function test_visibility_states(): void {
        $this->assertSame('hide', activity_repository::visibility_state(0, 1));
        $this->assertSame('show', activity_repository::visibility_state(1, 1));
        $this->assertSame('stealth', activity_repository::visibility_state(1, 0));
    }
}
