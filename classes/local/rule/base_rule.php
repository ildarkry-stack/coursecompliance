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
 * Classes for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance\local\rule;

defined('MOODLE_INTERNAL') || die();

/**
 * Base implementation shared by compliance rules.
 */
abstract class base_rule implements rule {
    /**
     * Create a rule.
     *
     * @param string $code Stable rule code.
     * @param string $component Audited component.
     * @param string $severity Rule severity.
     */
    public function __construct(
        private readonly string $code,
        private readonly string $component,
        private readonly string $severity
    ) {
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
