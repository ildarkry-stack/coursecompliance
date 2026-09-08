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
 * Rule implementation backed by a callback.
 */
final class callback_rule extends base_rule {
    /** @var \Closure */
    private \Closure $callback;

    /**
     * Create a callback-backed rule.
     *
     * @param string $code Stable rule code.
     * @param string $component Audited component.
     * @param string $severity Rule severity.
     * @param \Closure $callback Evaluation callback.
     */
    public function __construct(string $code, string $component, string $severity, \Closure $callback) {
        parent::__construct($code, $component, $severity);
        $this->callback = $callback;
    }

    /**
     * Evaluate the rule callback.
     *
     * @param array $context Normalized audit context.
     * @param array $config Profile configuration.
     * @return violation|null
     */
    public function evaluate(array $context, array $config): ?violation {
        return ($this->callback)($context, $config);
    }
}
