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
 * Lightweight scan performance measurements.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Captures worker duration, database activity and peak memory without requiring profiling extensions.
 */
final class scan_performance {
    /**
     * Start a measurement snapshot.
     *
     * @return array Measurement state.
     */
    public static function start(): array {
        return [
            'time' => microtime(true),
            'queries' => self::query_count(),
        ];
    }

    /**
     * Finish a measurement snapshot.
     *
     * @param array $start Start state returned by {@see self::start()}.
     * @return array
     */
    public static function finish(array $start): array {
        $duration = max(0.0, microtime(true) - (float)($start['time'] ?? microtime(true)));
        $queries = max(0, self::query_count() - (int)($start['queries'] ?? 0));
        return [
            'durationms' => (int)round($duration * 1000),
            'dbqueries' => $queries,
            'peakmemory' => (int)memory_get_peak_usage(true),
        ];
    }

    /**
     * Persist metrics on a scan row when the schema supports them.
     *
     * @param int $scanid Scan id.
     * @param array $metrics Metrics from {@see self::finish()}.
     */
    public static function store(int $scanid, array $metrics): void {
        global $DB;

        $previous = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $record = (object)[
            'id' => $scanid,
            'durationms' => (int)$previous->durationms + (int)($metrics['durationms'] ?? 0),
            'dbqueries' => (int)$previous->dbqueries + (int)($metrics['dbqueries'] ?? 0),
            'peakmemory' => max((int)$previous->peakmemory, (int)($metrics['peakmemory'] ?? 0)),
        ];
        $DB->update_record('tool_coursecompliance_scan', $record);
    }

    /**
     * Best-effort Moodle database query counter, compatible across supported Moodle branches.
     *
     * @return int
     */
    private static function query_count(): int {
        global $DB;

        $reads = method_exists($DB, 'perf_get_reads') ? (int)$DB->perf_get_reads() : 0;
        $writes = method_exists($DB, 'perf_get_writes') ? (int)$DB->perf_get_writes() : 0;
        return $reads + $writes;
    }
}
