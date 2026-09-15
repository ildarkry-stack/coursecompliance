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
 * Tests for scan performance measurements.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Performance metric tests.
 *
 * @covers \tool_coursecompliance\scan_performance
 */
final class scan_performance_test extends \advanced_testcase {
    /**
     * Measurements are non-negative and include peak memory.
     */
    public function test_measurement_is_non_negative(): void {
        $this->resetAfterTest();
        $start = scan_performance::start();
        $this->getDataGenerator()->create_category();
        $metrics = scan_performance::finish($start);

        $this->assertGreaterThanOrEqual(0, $metrics['durationms']);
        $this->assertGreaterThanOrEqual(0, $metrics['dbqueries']);
        $this->assertGreaterThan(0, $metrics['peakmemory']);
    }
}
