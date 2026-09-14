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
 * Tests for portable availability Blueprint conversion.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Availability Blueprint tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursecompliance\availability_blueprint
 */
final class availability_blueprint_test extends \advanced_testcase {
    /**
     * Course-specific IDs are converted to stable assessment idnumbers and back.
     */
    public function test_normalise_and_denormalise_round_trip(): void {
        $maps = [
            'cmtoidnumber' => [11 => 'exam', 12 => 'resit1'],
            'idnumbertocm' => ['exam' => 11, 'resit1' => 12],
            'gradeidtoidnumber' => [21 => 'exam'],
            'idnumbertogradeid' => ['exam' => 21],
            'groupidtolabel' => [],
            'labeltogroupid' => [],
            'groupingidtolabel' => [],
            'labeltogroupingid' => [],
        ];
        $raw = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => 11, 'e' => 2],
                ['type' => 'grade', 'id' => 21, 'max' => 60.0],
                ['type' => 'date', 'd' => '>=', 't' => 1700000000],
            ],
            'showc' => [true, true, true],
        ]);

        $portable = availability_blueprint::normalise_json($raw, $maps);
        $decoded = json_decode($portable, true);
        $this->assertSame('exam', $decoded['c'][0]['cmidnumber']);
        $this->assertSame('exam', $decoded['c'][1]['gradeidnumber']);
        $this->assertArrayNotHasKey('cm', $decoded['c'][0]);
        $this->assertArrayNotHasKey('id', $decoded['c'][1]);

        $restored = json_decode(availability_blueprint::denormalise_json($portable, $maps), true);
        $this->assertSame(11, $restored['c'][0]['cm']);
        $this->assertSame(21, $restored['c'][1]['id']);
    }

    /**
     * An empty native Moodle rule tree is stored as no restriction.
     */
    public function test_empty_tree_becomes_empty_string(): void {
        $maps = [
            'cmtoidnumber' => [],
            'idnumbertocm' => [],
            'gradeidtoidnumber' => [],
            'idnumbertogradeid' => [],
            'groupidtolabel' => [],
            'labeltogroupid' => [],
            'groupingidtolabel' => [],
            'labeltogroupingid' => [],
        ];
        $this->assertSame('', availability_blueprint::normalise_json('{"op":"&","c":[],"showc":[]}', $maps));
    }

    /**
     * Unresolved activity and grade references are rejected before auto-fix.
     */
    public function test_resolved_json_requires_positive_dependency_ids(): void {
        $this->assertTrue(availability_blueprint::is_resolved_json(''));
        $this->assertTrue(availability_blueprint::is_resolved_json(
            '{"op":"&","c":[{"type":"completion","cm":11,"e":2}],"showc":[true]}'
        ));
        $this->assertFalse(availability_blueprint::is_resolved_json(
            '{"op":"&","c":[{"type":"completion","cm":0,"e":2}],"showc":[true]}'
        ));
        $this->assertFalse(availability_blueprint::is_resolved_json(
            '{"op":"&","c":[{"type":"grade","id":0}],"showc":[true]}'
        ));
    }

}
