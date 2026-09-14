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
 * Tests for report chart factories.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Tests for report chart factories.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursecompliance\report_charts
 */
final class report_charts_test extends \advanced_testcase {
    /**
     * Severity chart should be omitted when there are no issues.
     */
    public function test_severity_returns_null_without_data(): void {
        $this->assertNull(report_charts::severity(0, 0, 0));
    }

    /**
     * Severity chart should use semantic colours in severity order.
     */
    public function test_severity_chart_uses_semantic_colours(): void {
        $chart = report_charts::severity(3, 2, 1);
        $this->assertInstanceOf(\core\chart_pie::class, $chart);

        $series = $chart->get_series();
        $this->assertCount(1, $series);
        $this->assertSame(['#dc3545', '#ffc107', '#0d6efd'], $series[0]->get_colors());
    }

    /**
     * Course readiness chart should use green for ready and red for courses requiring attention.
     */
    public function test_course_readiness_chart_uses_semantic_colours(): void {
        $chart = report_charts::course_readiness(8, 2);
        $this->assertInstanceOf(\core\chart_pie::class, $chart);

        $series = $chart->get_series();
        $this->assertCount(1, $series);
        $this->assertSame(['#198754', '#dc3545'], $series[0]->get_colors());
    }

    /**
     * Readiness chart should contain one label per populated assessment period.
     */
    public function test_readiness_chart_uses_populated_periods(): void {
        $periods = [
            'exam' => ['total' => 3, 'ready' => 1, 'warning' => 1, 'critical' => 1, 'missing' => 0],
            'resit1' => ['total' => 0, 'ready' => 0, 'warning' => 0, 'critical' => 0, 'missing' => 0],
            'resit2' => ['total' => 2, 'ready' => 2, 'warning' => 0, 'critical' => 0, 'missing' => 0],
        ];

        $chart = report_charts::readiness($periods);
        $this->assertInstanceOf(\core\chart_bar::class, $chart);
        $this->assertCount(2, $chart->get_labels());
        $this->assertCount(4, $chart->get_series());

        $series = $chart->get_series();
        $this->assertSame('#198754', $series[0]->get_color());
        $this->assertSame('#ffc107', $series[1]->get_color());
        $this->assertSame('#dc3545', $series[2]->get_color());
        $this->assertSame('#6c757d', $series[3]->get_color());
    }
}
