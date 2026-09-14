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
 * Native Moodle charts used by Course Compliance reports.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Chart factory for Course Compliance reports.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_charts {
    /**
     * Create a severity distribution doughnut chart.
     *
     * @param int $critical Critical issue count.
     * @param int $warnings Warning issue count.
     * @param int $notices Notice count.
     * @return \core\chart_pie|null Chart or null when there is no data.
     */
    public static function severity(int $critical, int $warnings, int $notices): ?\core\chart_pie {
        if (($critical + $warnings + $notices) === 0) {
            return null;
        }
        $chart = new \core\chart_pie();
        $chart->set_doughnut(true);
        $chart->set_title(get_string('chart_severity_title', 'tool_coursecompliance'));
        $chart->set_labels([
            get_string('critical', 'tool_coursecompliance'),
            get_string('warnings', 'tool_coursecompliance'),
            get_string('notices', 'tool_coursecompliance'),
        ]);
        $series = new \core\chart_series(
            get_string('problems', 'tool_coursecompliance'),
            [$critical, $warnings, $notices]
        );
        $series->set_colors(['#dc3545', '#ffc107', '#0d6efd']);
        $chart->add_series($series);
        return $chart;
    }

    /**
     * Create a problem-kind doughnut chart.
     *
     * @param int $missing Missing requirement count.
     * @param int $mismatch Configuration mismatch count.
     * @return \core\chart_pie|null Chart or null when there is no data.
     */
    public static function problem_kinds(int $missing, int $mismatch): ?\core\chart_pie {
        if (($missing + $mismatch) === 0) {
            return null;
        }
        $chart = new \core\chart_pie();
        $chart->set_doughnut(true);
        $chart->set_title(get_string('chart_problemkind_title', 'tool_coursecompliance'));
        $chart->set_labels([
            get_string('missingitems', 'tool_coursecompliance'),
            get_string('configurationmismatches', 'tool_coursecompliance'),
        ]);
        $series = new \core\chart_series(
            get_string('problems', 'tool_coursecompliance'),
            [$missing, $mismatch]
        );
        $series->set_colors(['#dc3545', '#fd7e14']);
        $chart->add_series($series);
        return $chart;
    }

    /**
     * Create a stacked readiness chart for exam and resits.
     *
     * @param array $periods Aggregated readiness periods.
     * @return \core\chart_bar|null Chart or null when there is no data.
     */
    public static function readiness(array $periods): ?\core\chart_bar {
        $labels = [];
        $ready = [];
        $warnings = [];
        $critical = [];
        $missing = [];
        foreach (['exam', 'resit1', 'resit2'] as $type) {
            $period = $periods[$type] ?? [];
            if (empty($period['total'])) {
                continue;
            }
            $labels[] = get_string('period_' . $type, 'tool_coursecompliance');
            $ready[] = (int)($period['ready'] ?? 0);
            $warnings[] = (int)($period['warning'] ?? 0);
            $critical[] = (int)($period['critical'] ?? 0);
            $missing[] = (int)($period['missing'] ?? 0);
        }
        if (!$labels) {
            return null;
        }
        $chart = new \core\chart_bar();
        $chart->set_stacked(true);
        $chart->set_title(get_string('chart_readiness_title', 'tool_coursecompliance'));
        $chart->set_labels($labels);
        $readyseries = new \core\chart_series(get_string('ready', 'tool_coursecompliance'), $ready);
        $readyseries->set_color('#198754');
        $chart->add_series($readyseries);

        $warningseries = new \core\chart_series(get_string('warnings', 'tool_coursecompliance'), $warnings);
        $warningseries->set_color('#ffc107');
        $chart->add_series($warningseries);

        $criticalseries = new \core\chart_series(get_string('critical', 'tool_coursecompliance'), $critical);
        $criticalseries->set_color('#dc3545');
        $chart->add_series($criticalseries);

        $missingseries = new \core\chart_series(get_string('missing', 'tool_coursecompliance'), $missing);
        $missingseries->set_color('#6c757d');
        $chart->add_series($missingseries);
        return $chart;
    }

    /**
     * Create a course readiness doughnut chart for the latest audit.
     *
     * @param int $ready Ready course count.
     * @param int $notready Courses requiring attention.
     * @return \core\chart_pie|null Chart or null when there is no data.
     */
    public static function course_readiness(int $ready, int $notready): ?\core\chart_pie {
        if (($ready + $notready) === 0) {
            return null;
        }
        $chart = new \core\chart_pie();
        $chart->set_doughnut(true);
        $chart->set_title(get_string('chart_course_readiness_title', 'tool_coursecompliance'));
        $chart->set_labels([
            get_string('readycourses', 'tool_coursecompliance'),
            get_string('notreadycourses', 'tool_coursecompliance'),
        ]);
        $series = new \core\chart_series(
            get_string('courses', 'tool_coursecompliance'),
            [$ready, $notready]
        );
        $series->set_colors(['#198754', '#dc3545']);
        $chart->add_series($series);
        return $chart;
    }

    /**
     * Create a recent scan trend chart.
     *
     * @param array $scans Completed scans ordered oldest to newest.
     * @return \core\chart_line|null Chart or null when fewer than two scans exist.
     */
    public static function scan_trend(array $scans): ?\core\chart_line {
        if (count($scans) < 2) {
            return null;
        }
        $labels = [];
        $critical = [];
        $warnings = [];
        foreach ($scans as $scan) {
            $labels[] = userdate((int)$scan->timecreated, get_string('strftimedateshort'));
            $critical[] = (int)$scan->critical;
            $warnings[] = (int)$scan->warnings;
        }
        $chart = new \core\chart_line();
        $chart->set_title(get_string('chart_scantrend_title', 'tool_coursecompliance'));
        $chart->set_labels($labels);
        $criticalseries = new \core\chart_series(get_string('critical', 'tool_coursecompliance'), $critical);
        $criticalseries->set_color('#dc3545');
        $chart->add_series($criticalseries);

        $warningseries = new \core\chart_series(get_string('warnings', 'tool_coursecompliance'), $warnings);
        $warningseries->set_color('#ffc107');
        $chart->add_series($warningseries);
        return $chart;
    }
}
