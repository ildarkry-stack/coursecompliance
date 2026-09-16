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
 * Privacy provider tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use tool_coursecompliance\privacy\provider;

/**
 * Privacy API regression tests.
 *
 * @covers \tool_coursecompliance\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * The provider must expose administrators with stored data in the system context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $profileid = $this->create_profile();

        $this->create_personal_records((int)$userone->id, $profileid);
        $this->create_personal_records((int)$usertwo->id, $profileid);

        $systemcontext = \context_system::instance();
        $userlist = new userlist($systemcontext, 'tool_coursecompliance');
        provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        sort($actual);
        $expected = [(int)$userone->id, (int)$usertwo->id];
        sort($expected);
        $this->assertSame($expected, $actual);

        $usercontext = \context_user::instance((int)$userone->id);
        $othercontextlist = new userlist($usercontext, 'tool_coursecompliance');
        provider::get_users_in_context($othercontextlist);
        $this->assertEmpty($othercontextlist->get_userids());
    }

    /**
     * Bulk privacy deletion must remove one user's parent and child records without touching another user.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $profileid = $this->create_profile();

        $one = $this->create_personal_records((int)$userone->id, $profileid);
        $two = $this->create_personal_records((int)$usertwo->id, $profileid);

        $approved = new approved_userlist(
            \context_system::instance(),
            'tool_coursecompliance',
            [(int)$userone->id]
        );
        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('tool_coursecompliance_scan', ['id' => $one['scanid']]));
        $this->assertFalse($DB->record_exists('tool_coursecompliance_result', ['scanid' => $one['scanid']]));
        $this->assertFalse($DB->record_exists('tool_coursecompliance_act_batch', ['id' => $one['batchid']]));
        $this->assertFalse($DB->record_exists('tool_coursecompliance_act_change', ['batchid' => $one['batchid']]));
        $this->assertFalse($DB->record_exists('tool_coursecompliance_exception', ['id' => $one['exceptionid']]));

        $this->assertTrue($DB->record_exists('tool_coursecompliance_scan', ['id' => $two['scanid']]));
        $this->assertTrue($DB->record_exists('tool_coursecompliance_result', ['scanid' => $two['scanid']]));
        $this->assertTrue($DB->record_exists('tool_coursecompliance_act_batch', ['id' => $two['batchid']]));
        $this->assertTrue($DB->record_exists('tool_coursecompliance_act_change', ['batchid' => $two['batchid']]));
        $this->assertTrue($DB->record_exists('tool_coursecompliance_exception', ['id' => $two['exceptionid']]));
    }

    /**
     * Create a minimal compliance profile.
     *
     * @return int Profile id.
     */
    private function create_profile(): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('tool_coursecompliance_profile', (object)[
            'name' => 'Privacy test profile',
            'description' => '',
            'enabled' => 1,
            'configjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Create representative personal records and dependent audit/operation rows.
     *
     * @param int $userid User id.
     * @param int $profileid Profile id.
     * @return array Record ids.
     */
    private function create_personal_records(int $userid, int $profileid): array {
        global $DB;

        $now = time();
        $scanid = (int)$DB->insert_record('tool_coursecompliance_scan', (object)[
            'profileid' => $profileid,
            'categoryid' => 1,
            'userid' => $userid,
            'status' => 'complete',
            'timecreated' => $now,
        ]);
        $DB->insert_record('tool_coursecompliance_result', (object)[
            'scanid' => $scanid,
            'courseid' => 2,
            'component' => 'course',
            'assessmenttype' => 'course',
            'rulecode' => 'privacy_test',
            'severity' => 'notice',
            'message' => 'Privacy test finding',
            'timecreated' => $now,
        ]);

        $batchid = (int)$DB->insert_record('tool_coursecompliance_act_batch', (object)[
            'userid' => $userid,
            'categoryid' => 1,
            'action' => 'privacy_test',
            'status' => 'complete',
            'timecreated' => $now,
        ]);
        $DB->insert_record('tool_coursecompliance_act_change', (object)[
            'batchid' => $batchid,
            'cmid' => 1,
            'courseid' => 2,
            'modname' => 'quiz',
            'instanceid' => 1,
            'fieldname' => 'timeopen',
            'status' => 'applied',
            'timecreated' => $now,
        ]);

        $exceptionid = (int)$DB->insert_record('tool_coursecompliance_exception', (object)[
            'profileid' => $profileid,
            'courseid' => 2,
            'rulecode' => 'privacy_test',
            'component' => 'course',
            'reason' => 'Privacy test reason',
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return [
            'scanid' => $scanid,
            'batchid' => $batchid,
            'exceptionid' => $exceptionid,
        ];
    }
}
