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
 * Tests for Blueprint rule form mapping.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Blueprint rule mapper tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_coursecompliance\blueprint_rule_mapper
 */
final class blueprint_rule_mapper_test extends \advanced_testcase {
    /**
     * Nested rules survive a form round trip.
     */
    public function test_round_trip_preserves_review_matrix(): void {
        $rules = profile_config::blueprint_rule_defaults();
        $rules['content']['pagekeyword'] = 'lecture';
        $rules['assessments']['exam']['review_manual']['reviewmarks']['closed'] = 1;
        $rules['assessments']['exam']['review_manual']['reviewmaxmarks']['immediately'] = 1;
        $formdata = blueprint_rule_mapper::to_form_data($rules, 7);
        $roundtrip = blueprint_rule_mapper::from_form_data($formdata, $rules);

        $this->assertSame('lecture', $roundtrip['content']['pagekeyword']);
        $this->assertSame(1, $roundtrip['assessments']['exam']['review_manual']['reviewmarks']['closed']);
        $this->assertSame(
            1,
            $roundtrip['assessments']['exam']['review_manual']['reviewmaxmarks']['immediately']
        );
        $this->assertSame(7, $formdata->profileid);
    }
}
