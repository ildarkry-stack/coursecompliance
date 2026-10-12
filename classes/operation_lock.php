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
 * Exclusive ownership of plugin operations.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Serialises operations sharing a logical resource across cron workers.
 */
final class operation_lock {
    /**
     * Execute an operation while holding a Moodle lock.
     *
     * @param string $resource Logical resource name.
     * @param callable $callback Operation.
     * @return mixed Operation result.
     */
    public static function run(string $resource, callable $callback): mixed {
        $factory = \core\lock\lock_config::get_lock_factory('tool_coursecompliance');
        $lock = $factory->get_lock($resource, 10);
        if (!$lock) {
            throw new \moodle_exception('operationbusy', 'tool_coursecompliance');
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
