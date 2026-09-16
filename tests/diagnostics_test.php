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
 * Diagnostics tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Runtime diagnostics regression tests.
 *
 * @covers \tool_coursecompliance\diagnostics
 */
final class diagnostics_test extends \advanced_testcase {
    /**
     * Supported Moodle branches are explicit and bounded.
     */
    public function test_supported_moodle_branches(): void {
        $this->assertFalse(diagnostics::is_supported_branch(499));
        $this->assertTrue(diagnostics::is_supported_branch(500));
        $this->assertTrue(diagnostics::is_supported_branch(501));
        $this->assertTrue(diagnostics::is_supported_branch(502));
        $this->assertFalse(diagnostics::is_supported_branch(503));
    }

    /**
     * Minimum PHP follows Moodle branch requirements used by the compatibility matrix.
     */
    public function test_minimum_php_by_branch(): void {
        $this->assertSame('8.2.0', diagnostics::minimum_php_for_branch(500));
        $this->assertSame('8.2.0', diagnostics::minimum_php_for_branch(501));
        $this->assertSame('8.3.0', diagnostics::minimum_php_for_branch(502));
    }

    /**
     * Snapshot can be produced on every supported CI environment.
     */
    public function test_snapshot_contains_release_support_information(): void {
        global $CFG;

        $this->resetAfterTest();
        $snapshot = diagnostics::snapshot();

        $this->assertSame((int)$CFG->branch, $snapshot['branch']);
        $this->assertSame('0.19.0-rc1', $snapshot['pluginrelease']);
        $this->assertArrayHasKey('moodle', $snapshot['checks']);
        $this->assertArrayHasKey('php', $snapshot['checks']);
        $this->assertArrayHasKey('database', $snapshot['checks']);
        $this->assertArrayHasKey('apis', $snapshot['checks']);
        $this->assertTrue($snapshot['checks']['moodle']['ok']);
        $this->assertTrue($snapshot['checks']['php']['ok']);
        $this->assertTrue($snapshot['checks']['database']['ok']);
        $this->assertTrue($snapshot['checks']['apis']['ok']);
    }
}
