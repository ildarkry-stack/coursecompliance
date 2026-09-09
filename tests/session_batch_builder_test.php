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
 * Tests for the Course compliance plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Integration tests for session scheduling preview batches.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_coursecompliance\session_batch_builder
 */
final class session_batch_builder_test extends \advanced_testcase {
    /**
     * A Quiz target should create immutable open/close preview changes.
     */
    public function test_create_quiz_preview(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Exam',
            'cmidnumber' => 'exam',
            'timeopen' => 1000,
            'timeclose' => 2000,
        ]);

        $data = $this->form_data($category->id);
        $batchid = (new session_batch_builder())->create($data, $USER->id);
        $batch = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $batchid], '*', MUST_EXIST);
        $this->assertSame(2, (int)$batch->totalchanges);
        $this->assertCount(2, $DB->get_records('tool_coursecompliance_act_change', ['batchid' => $batchid]));
    }

    /**
     * Reusing one idnumber for exam and resit must be rejected.
     */
    public function test_duplicate_period_target_is_rejected(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $category->id]);
        $data = $this->form_data($category->id);
        $data->update_resit1 = 1;
        $data->resit1_open = 5000;
        $data->resit1_close = 6000;
        $data->resit1_quizidnumber = 'exam';
        $data->resit1_assignidnumber = '';

        $this->expectException(\moodle_exception::class);
        (new session_batch_builder())->create($data, $USER->id);
    }

    /**
     * Build minimal planner data.
     *
     * @param int $categoryid Course category id.
     * @return \stdClass
     */
    private function form_data(int $categoryid): \stdClass {
        return (object)[
            'categoryid' => $categoryid,
            'includesubcategories' => 0,
            'adjustassignmentdates' => 1,
            'update_exam' => 1,
            'exam_open' => 3000,
            'exam_close' => 4000,
            'exam_quizidnumber' => 'exam',
            'exam_assignidnumber' => '',
            'update_resit1' => 0,
            'update_resit2' => 0,
        ];
    }
}
