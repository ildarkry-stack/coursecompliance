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
 * Rule violation value object.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\local\rule;

/**
 * Immutable description of one rule violation.
 */
final class violation {
    /** @var string Human-readable violation message. */
    public readonly string $message;

    /** @var string Expected value. */
    public readonly string $expected;

    /** @var string Actual value. */
    public readonly string $actual;

    /** @var int Related object id. */
    public readonly int $objectid;

    /** @var string Related object name. */
    public readonly string $objectname;

    /**
     * Create a violation value object.
     *
     * @param string $message Human-readable violation message.
     * @param string $expected Expected value.
     * @param string $actual Actual value.
     * @param int $objectid Related object id.
     * @param string $objectname Related object name.
     */
    public function __construct(
        string $message,
        string $expected = '',
        string $actual = '',
        int $objectid = 0,
        string $objectname = ''
    ) {
        $this->message = $message;
        $this->expected = $expected;
        $this->actual = $actual;
        $this->objectid = $objectid;
        $this->objectname = $objectname;
    }
}
