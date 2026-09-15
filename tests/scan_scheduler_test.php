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
 * Tests for scan batching.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Scan scheduler tests.
 *
 * @covers \tool_coursecompliance\scan_scheduler
 */
final class scan_scheduler_test extends \advanced_testcase {
    /**
     * A large selected scope is split into independent worker scans.
     */
    public function test_selected_courses_are_split_into_batches(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('scanbatchsize', 5, 'tool_coursecompliance');

        $category = $this->getDataGenerator()->create_category();
        $courseids = [];
        for ($i = 0; $i < 12; $i++) {
            $courseids[] = $this->getDataGenerator()->create_course([
                'category' => $category->id,
            ])->id;
        }
        $profileid = $DB->insert_record('tool_coursecompliance_profile', (object)[
            'name' => 'Scale test',
            'description' => '',
            'enabled' => 1,
            'configjson' => profile_config::encode(profile_config::defaults()),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $scanid = $DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => $category->id,
            'courselimit' => 0,
            'scopejson' => json_encode(['courseids' => $courseids]),
            'scantype' => 'audit',
            'parentid' => 0,
            'batchno' => 0,
            'batchtotal' => 0,
            'userid' => $USER->id,
            'status' => 'queued',
            'timecreated' => time(),
        ]);

        $this->assertSame(3, scan_scheduler::queue($scanid, $courseids, (int)$USER->id));
        $children = array_values($DB->get_records(
            'tool_coursecompliance_scan',
            ['parentid' => $scanid],
            'batchno ASC'
        ));
        $this->assertCount(3, $children);
        $this->assertSame([1, 2, 3], array_map(static fn ($row): int => (int)$row->batchno, $children));

        $sizes = [];
        foreach ($children as $child) {
            $scope = json_decode((string)$child->scopejson, true);
            $sizes[] = count($scope['courseids'] ?? []);
        }
        $this->assertSame([5, 5, 2], $sizes);

        $parent = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $this->assertSame('running', $parent->status);
        $this->assertSame(3, (int)$parent->batchtotal);
        $this->assertSame(12, (int)$parent->courses);
    }
}
