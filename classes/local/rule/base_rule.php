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


namespace tool_coursecompliance\local\rule;

defined('MOODLE_INTERNAL') || die();

abstract class base_rule implements rule {
    public function __construct(
        private readonly string $code,
        private readonly string $component,
        private readonly string $severity
    ) {
    }

    public function get_code(): string {
        return $this->code;
    }
    public function get_component(): string {
        return $this->component;
    }
    public function get_severity(): string {
        return $this->severity;
    }
}
