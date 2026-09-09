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
 * Base compliance rule implementation.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\local\rule;

/**
 * Base implementation shared by compliance rules.
 */
abstract class base_rule implements rule {
    /** @var string Stable rule code. */
    private readonly string $code;

    /** @var string Audited component. */
    private readonly string $component;

    /** @var string Rule severity. */
    private readonly string $severity;

    /**
     * Create a rule.
     *
     * @param string $code Stable rule code.
     * @param string $component Audited component.
     * @param string $severity Rule severity.
     */
    public function __construct(string $code, string $component, string $severity) {
        $this->code = $code;
        $this->component = $component;
        $this->severity = $severity;
    }

    /**
     * Get the stable rule code.
     *
     * @return string
     */
    public function get_code(): string {
        return $this->code;
    }

    /**
     * Get the audited component.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get the rule severity.
     *
     * @return string
     */
    public function get_severity(): string {
        return $this->severity;
    }
}
