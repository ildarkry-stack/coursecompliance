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

namespace tool_coursecompliance;

/**
 * Applies previewed activity operations safely.
 *
 * Every course module is processed in its own transaction. Before writing,
 * the current value is compared with the value captured by the preview.
 * This prevents a queued bulk operation from overwriting a teacher's newer edit.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_manager {
    /**
     * Apply a queued batch.
     *
     * @param int $batchid Batch id.
     */
    public function apply_batch(int $batchid): void {
        global $DB;

        $batch = $DB->get_record('tool_cc_act_batch', ['id' => $batchid], '*', MUST_EXIST);
        if (!in_array($batch->status, ['queued', 'running'], true)) {
            throw new \moodle_exception('batchnotqueued', 'tool_coursecompliance');
        }
        $batch->status = 'running';
        $batch->timestarted = $batch->timestarted ?: time();
        $DB->update_record('tool_cc_act_batch', $batch);

        $cmids = $DB->get_fieldset_sql(
            'SELECT DISTINCT cmid FROM {tool_cc_act_change} WHERE batchid = :batchid ORDER BY cmid ASC',
            ['batchid' => $batchid]
        );
        $applied = 0;
        $skipped = 0;
        $failed = 0;
        $repository = new activity_repository();

        foreach (array_chunk(array_map('intval', $cmids), 200) as $cmchunk) {
            if (!$cmchunk) {
                continue;
            }
            $activities = $repository->get_by_cmids($cmchunk);
            [$cmsql, $cmparams] = $DB->get_in_or_equal($cmchunk, SQL_PARAMS_NAMED, 'batchcm');
            $params = $cmparams + ['batchid' => $batchid];
            $changes = $DB->get_records_select(
                'tool_cc_act_change',
                "batchid = :batchid AND cmid $cmsql",
                $params,
                'cmid ASC, id ASC'
            );
            $grouped = [];
            foreach ($changes as $change) {
                $grouped[(int)$change->cmid][] = $change;
            }

            foreach ($grouped as $cmid => $cmchanges) {
                try {
                    if (empty($activities[$cmid])) {
                        $this->mark_group($cmchanges, 'failed', get_string('activitymissing', 'tool_coursecompliance'));
                        $failed += count($cmchanges);
                        continue;
                    }
                    $activity = $activities[$cmid];
                    $conflict = false;
                    foreach ($cmchanges as $change) {
                        $context = \context_module::instance($cmid);
                        if ($change->fieldname === 'visibilitystate') {
                            require_capability('moodle/course:activityvisibility', $context);
                        } else {
                            require_capability('moodle/course:manageactivities', $context);
                        }
                        $actual = $this->current_value($activity, $change->fieldname);
                        if ((string)$actual !== (string)$change->oldvalue) {
                            $conflict = true;
                            break;
                        }
                    }
                    if ($conflict) {
                        $this->mark_group($cmchanges, 'skipped', get_string('conflictchanged', 'tool_coursecompliance'));
                        $skipped += count($cmchanges);
                        continue;
                    }

                    $transaction = $DB->start_delegated_transaction();
                    try {
                        $this->apply_activity_changes($activity, $cmchanges);
                        $transaction->allow_commit();
                    } catch (\Throwable $e) {
                        $transaction->rollback($e);
                    }
                    $this->mark_group($cmchanges, 'applied', '');
                    $applied += count($cmchanges);
                } catch (\Throwable $e) {
                    $this->mark_group($cmchanges, 'failed', $e->getMessage());
                    $failed += count($cmchanges);
                }
            }
        }

        $batch = $DB->get_record('tool_cc_act_batch', ['id' => $batchid], '*', MUST_EXIST);
        $batch->applied = $applied;
        $batch->skipped = $skipped;
        $batch->failed = $failed;
        if ($failed === (int)$batch->totalchanges && !$applied && !$skipped) {
            $batch->status = 'failed';
        } else {
            $batch->status = ($skipped || $failed) ? 'partial' : 'complete';
        }
        $batch->timefinished = time();
        $DB->update_record('tool_cc_act_batch', $batch);
    }

    /**
     * Apply all changes for one course module.
     *
     * @param \stdClass $activity Normalized activity.
     * @param \stdClass[] $changes Changes.
     */
    private function apply_activity_changes(\stdClass $activity, array $changes): void {
        global $CFG, $DB;

        $datechanges = [];
        $rename = null;
        $visibility = null;
        foreach ($changes as $change) {
            if (in_array($change->fieldname, activity_repository::date_fields($activity->modname), true)) {
                $datechanges[$change->fieldname] = (int)$change->newvalue;
            } elseif ($change->fieldname === 'name') {
                $rename = $change->newvalue;
            } elseif ($change->fieldname === 'visibilitystate') {
                $visibility = $change->newvalue;
            }
        }

        if ($datechanges) {
            $instance = $DB->get_record($activity->modname, ['id' => $activity->instanceid], '*', MUST_EXIST);
            $oldclose = $activity->modname === 'quiz' ? (int)$instance->timeclose : null;
            foreach ($datechanges as $field => $value) {
                $instance->{$field} = $value;
            }
            if (property_exists($instance, 'timemodified')) {
                $instance->timemodified = time();
            }
            $DB->update_record($activity->modname, $instance);

            $cm = get_coursemodule_from_id($activity->modname, $activity->cmid, $activity->courseid, false, MUST_EXIST);
            if ($activity->modname === 'quiz') {
                require_once($CFG->dirroot . '/mod/quiz/lib.php');
                require_once($CFG->dirroot . '/mod/quiz/locallib.php');
                quiz_update_events($instance);
                $completionexpected = !empty($cm->completionexpected) ? (int)$cm->completionexpected : null;
                \core_completion\api::update_completion_date_event(
                    $cm->id,
                    'quiz',
                    $instance,
                    $completionexpected
                );
                quiz_grade_item_update($instance);
                if ($oldclose !== (int)$instance->timeclose) {
                    quiz_update_open_attempts(['quizid' => $instance->id]);
                }
                quiz_delete_previews($instance);
            } else {
                require_once($CFG->dirroot . '/course/lib.php');
                course_module_update_calendar_events($activity->modname, $instance, $cm);
            }
            \core\event\course_module_updated::create_from_cm($cm)->trigger();
            \course_modinfo::purge_course_module_cache($activity->courseid, $activity->cmid);
            rebuild_course_cache($activity->courseid, false, true);
        }

        if ($rename !== null) {
            require_once($CFG->dirroot . '/course/lib.php');
            set_coursemodule_name($activity->cmid, $rename);
        }

        if ($visibility !== null) {
            require_once($CFG->dirroot . '/course/lib.php');
            if ($visibility === 'stealth' && empty($CFG->allowstealth)) {
                throw new \moodle_exception('stealthdisabled', 'tool_coursecompliance');
            }
            if ($visibility === 'hide') {
                set_coursemodule_visible($activity->cmid, 0, 1);
            } elseif ($visibility === 'stealth') {
                set_coursemodule_visible($activity->cmid, 1, 0);
            } else {
                set_coursemodule_visible($activity->cmid, 1, 1);
            }
            $cm = get_coursemodule_from_id('', $activity->cmid, $activity->courseid, false, MUST_EXIST);
            \core\event\course_module_updated::create_from_cm($cm)->trigger();
        }
    }

    /**
     * Resolve current value from normalized activity.
     *
     * @param \stdClass $activity Activity.
     * @param string $field Field name.
     * @return string|int
     */
    private function current_value(\stdClass $activity, string $field): string|int {
        if (array_key_exists($field, $activity->dates)) {
            return (int)$activity->dates[$field];
        }
        if ($field === 'name') {
            return $activity->activityname;
        }
        if ($field === 'visibilitystate') {
            return $activity->visibilitystate;
        }
        throw new \coding_exception('Unsupported activity change field: ' . $field);
    }

    /**
     * Mark all changes in a course-module operation.
     *
     * @param \stdClass[] $changes Changes.
     * @param string $status New status.
     * @param string $message Optional message.
     */
    private function mark_group(array $changes, string $status, string $message): void {
        global $DB;
        foreach ($changes as $change) {
            $change->status = $status;
            $change->message = $message;
            $change->timeapplied = $status === 'applied' ? time() : 0;
            $DB->update_record('tool_cc_act_change', $change);
        }
    }
}
