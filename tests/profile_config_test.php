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

namespace tool_coursecompliance;

/**
 * Tests for profile configuration normalisation.
 *
 * @package    tool_coursecompliance
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_coursecompliance\profile_config
 */
final class profile_config_test extends \advanced_testcase {
    /**
     * A partial JSON profile must inherit safe defaults.
     */
    public function test_decode_merges_defaults(): void {
        $config = profile_config::decode('{"examidnumber":"final"}');
        $this->assertSame('final', $config['examidnumber']);
        $this->assertTrue($config['hideanswerswhileopen']);
        $this->assertSame(1, $config['minquestions']);
    }

    /**
     * Practice preset does not require a Quiz exam.
     */
    public function test_practice_preset_disables_quiz_requirement(): void {
        $config = profile_config::preset('practice');
        $this->assertFalse($config['requireexam']);
        $this->assertSame(0, $config['minquestions']);
    }
}
