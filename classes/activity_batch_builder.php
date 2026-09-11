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

use tool_coursecompliance\local\activity\date_helper;

/**
 * Creates immutable previews for activity operations.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_batch_builder {
    /**
     * Create a preview batch.
     *
     * @param string $action Operation name.
     * @param int[] $cmids Selected course modules.
     * @param array $options Operation options.
     * @param int $categoryid Source category.
     * @param array $filters Browser filters.
     * @param int $userid User creating the batch.
     * @return int Batch id.
     */
    public function create(
        string $action,
        array $cmids,
        array $options,
        int $categoryid,
        array $filters,
        int $userid
    ): int {
        global $DB;

        if (!in_array($action, ['save_dates', 'shift_dates', 'visibility', 'rename'], true)) {
            throw new \moodle_exception('invalidoperation', 'tool_coursecompliance');
        }
        $cmids = array_values(array_unique(array_filter(array_map('intval', $cmids))));
        if (!$cmids || count($cmids) > 200) {
            throw new \moodle_exception('selectionlimit', 'tool_coursecompliance');
        }

        $repository = new activity_repository();
        $activities = $repository->get_by_cmids($cmids);
        if (!$activities) {
            throw new \moodle_exception('noactivitiesselected', 'tool_coursecompliance');
        }
        $category = \core_course_category::get($categoryid, MUST_EXIST, true);
        $allowedcategories = array_merge([$category->id], $category->get_all_children_ids());

        $changes = [];
        foreach ($cmids as $cmid) {
            if (empty($activities[$cmid])) {
                continue;
            }
            $activity = $activities[$cmid];
            if (
                !in_array((int)$activity->categoryid, $allowedcategories, true)
                || (!empty($filters['courseid']) && (int)$activity->courseid !== (int)$filters['courseid'])
            ) {
                throw new \moodle_exception('activityoutsidescope', 'tool_coursecompliance');
            }
            $context = \context_module::instance($cmid);
            if ($action === 'visibility') {
                require_capability('moodle/course:activityvisibility', $context);
            } else {
                require_capability('moodle/course:manageactivities', $context);
            }
            $changes = array_merge($changes, $this->build_activity_changes($activity, $action, $options));
        }

        if (!$changes) {
            throw new \moodle_exception('nothingtochange', 'tool_coursecompliance');
        }

        $transaction = $DB->start_delegated_transaction();
        $batchid = $DB->insert_record('tool_coursecompliance_act_batch', (object)[
            'userid' => $userid,
            'categoryid' => $categoryid,
            'sourcebatchid' => 0,
            'action' => $action,
            'status' => 'preview',
            'filterjson' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary' => $this->summary($action, $options),
            'totalchanges' => count($changes),
            'applied' => 0,
            'skipped' => 0,
            'failed' => 0,
            'timecreated' => time(),
            'timestarted' => 0,
            'timefinished' => 0,
        ]);
        foreach ($changes as $change) {
            $change->batchid = $batchid;
            $DB->insert_record('tool_coursecompliance_act_change', $change);
        }
        $transaction->allow_commit();
        return $batchid;
    }

    /**
     * Create a safe rollback preview for a previously applied batch.
     *
     * Only changes whose current value still equals the value written by the
     * source batch are eligible. This prevents rollback from overwriting a
     * newer edit made by a teacher or administrator.
     *
     * @param int $sourcebatchid Source batch id.
     * @param int $userid User preparing the rollback.
     * @return int New rollback batch id.
     */
    public function create_rollback(int $sourcebatchid, int $userid): int {
        global $DB;

        $source = $DB->get_record('tool_coursecompliance_act_batch', ['id' => $sourcebatchid], '*', MUST_EXIST);
        if (!in_array($source->status, ['complete', 'partial'], true) || empty($source->applied)) {
            throw new \moodle_exception('batchnotrollbackable', 'tool_coursecompliance');
        }
        if ($source->action === 'rollback') {
            throw new \moodle_exception('rollbackofrollback', 'tool_coursecompliance');
        }

        $appliedchanges = $DB->get_records('tool_coursecompliance_act_change', [
            'batchid' => $sourcebatchid,
            'status' => 'applied',
        ], 'cmid ASC, id ASC');
        if (!$appliedchanges) {
            throw new \moodle_exception('nothingtorollback', 'tool_coursecompliance');
        }

        $cmids = array_values(array_unique(array_map(static fn ($change) => (int)$change->cmid, $appliedchanges)));
        $activities = (new activity_repository())->get_by_cmids($cmids);
        $changes = [];
        $conflicts = 0;

        foreach ($appliedchanges as $change) {
            $cmid = (int)$change->cmid;
            if (empty($activities[$cmid])) {
                $conflicts++;
                continue;
            }
            $activity = $activities[$cmid];
            $context = \context_module::instance($cmid);
            if ($change->fieldname === 'visibilitystate') {
                require_capability('moodle/course:activityvisibility', $context);
            } else {
                require_capability('moodle/course:manageactivities', $context);
            }

            $current = $this->current_value($activity, $change->fieldname);
            if ((string)$current !== (string)$change->newvalue) {
                $conflicts++;
                continue;
            }

            $changes[] = $this->make_change(
                $activity,
                $change->fieldname,
                (string)$current,
                (string)$change->oldvalue
            );
        }

        if (!$changes) {
            throw new \moodle_exception('nothingtorollback', 'tool_coursecompliance');
        }

        $transaction = $DB->start_delegated_transaction();
        $batchid = $DB->insert_record('tool_coursecompliance_act_batch', (object)[
            'userid' => $userid,
            'categoryid' => (int)$source->categoryid,
            'sourcebatchid' => $sourcebatchid,
            'action' => 'rollback',
            'status' => 'preview',
            'filterjson' => $source->filterjson,
            'summary' => get_string('batchsummary_rollback', 'tool_coursecompliance', (object)[
                'source' => $sourcebatchid,
                'eligible' => count($changes),
                'conflicts' => $conflicts,
            ]),
            'totalchanges' => count($changes),
            'applied' => 0,
            'skipped' => 0,
            'failed' => 0,
            'timecreated' => time(),
            'timestarted' => 0,
            'timefinished' => 0,
        ]);
        foreach ($changes as $change) {
            $change->batchid = $batchid;
            $DB->insert_record('tool_coursecompliance_act_change', $change);
        }
        $transaction->allow_commit();

        return $batchid;
    }

    /**
     * Get a normalized current value for a supported field.
     *
     * @param \stdClass $activity Activity.
     * @param string $field Field name.
     * @return string|int|float
     */
    private function current_value(\stdClass $activity, string $field): string|int|float {
        global $DB;

        if (is_array($activity->dates ?? null) && array_key_exists($field, $activity->dates)) {
            return (int)$activity->dates[$field];
        }
        if ($field === 'name') {
            return $activity->activityname;
        }
        if ($field === 'visibilitystate') {
            return $activity->visibilitystate;
        }
        if ($activity->modname === 'quiz' && in_array($field, activity_manager::quiz_setting_fields(), true)) {
            return $DB->get_field('quiz', $field, ['id' => $activity->instanceid], MUST_EXIST);
        }
        throw new \coding_exception('Unsupported activity change field: ' . $field);
    }

    /**
     * Build changes for one activity.
     *
     * @param \stdClass $activity Normalized activity.
     * @param string $action Operation name.
     * @param array $options Operation options.
     * @return \stdClass[]
     */
    private function build_activity_changes(\stdClass $activity, string $action, array $options): array {
        $changes = [];
        $dates = is_array($activity->dates ?? null) ? $activity->dates : [];
        if ($action === 'save_dates') {
            $newdates = [];
            foreach (activity_repository::date_fields($activity->modname) as $field) {
                $key = 'date_' . $activity->cmid . '_' . $field;
                if (!array_key_exists($key, $options)) {
                    $newdates[$field] = (int)($dates[$field] ?? 0);
                    continue;
                }
                $newdates[$field] = date_helper::from_input_value((string)$options[$key]);
            }
            $this->validate_dates($activity->modname, $newdates, $activity->activityname);
            foreach ($newdates as $field => $newvalue) {
                $oldvalue = (int)($dates[$field] ?? 0);
                if ($newvalue !== $oldvalue) {
                    $changes[] = $this->make_change($activity, $field, (string)$oldvalue, (string)$newvalue);
                }
            }
        } else if ($action === 'shift_dates') {
            $amount = (int)($options['shiftamount'] ?? 0);
            $unit = (string)($options['shiftunit'] ?? 'day');
            if ($amount === 0) {
                throw new \moodle_exception('shiftzeronotallowed', 'tool_coursecompliance');
            }
            $newdates = [];
            foreach (activity_repository::date_fields($activity->modname) as $field) {
                $oldvalue = (int)($dates[$field] ?? 0);
                $newdates[$field] = date_helper::shift($oldvalue, $amount, $unit);
                if ($oldvalue && $newdates[$field] !== $oldvalue) {
                    $changes[] = $this->make_change($activity, $field, (string)$oldvalue, (string)$newdates[$field]);
                }
            }
            $this->validate_dates($activity->modname, $newdates, $activity->activityname);
        } else if ($action === 'visibility') {
            $newstate = (string)($options['visibilitystate'] ?? '');
            if (!in_array($newstate, ['show', 'hide', 'stealth'], true)) {
                throw new \moodle_exception('invalidvisibilitystate', 'tool_coursecompliance');
            }
            global $CFG;
            if ($newstate === 'stealth' && empty($CFG->allowstealth)) {
                throw new \moodle_exception('stealthdisabled', 'tool_coursecompliance');
            }
            if ($newstate !== $activity->visibilitystate) {
                $changes[] = $this->make_change(
                    $activity,
                    'visibilitystate',
                    $activity->visibilitystate,
                    $newstate
                );
            }
        } else if ($action === 'rename') {
            $find = (string)($options['findtext'] ?? '');
            $replace = (string)($options['replacetext'] ?? '');
            if ($find === '') {
                throw new \moodle_exception('findtextrequired', 'tool_coursecompliance');
            }
            $newname = str_replace($find, $replace, $activity->activityname);
            if ($newname === '') {
                throw new \moodle_exception('emptyactivityname', 'tool_coursecompliance', '', $activity->activityname);
            }
            if (\core_text::strlen($newname) > 1333) {
                throw new \moodle_exception('maximumchars', 'moodle', '', 1333);
            }
            if ($newname !== $activity->activityname) {
                $changes[] = $this->make_change($activity, 'name', $activity->activityname, $newname);
            }
        }
        return $changes;
    }

    /**
     * Validate common timeline relationships.
     *
     * @param string $modname Activity type.
     * @param array $dates Date fields.
     * @param string $activityname Activity name.
     */
    private function validate_dates(string $modname, array $dates, string $activityname): void {
        if ($modname === 'quiz') {
            if (!empty($dates['timeopen']) && !empty($dates['timeclose']) && $dates['timeclose'] <= $dates['timeopen']) {
                throw new \moodle_exception('invalidquizdates', 'tool_coursecompliance', '', $activityname);
            }
        } else if ($modname === 'assign') {
            if (
                !empty($dates['allowsubmissionsfromdate'])
                && !empty($dates['duedate'])
                && $dates['duedate'] < $dates['allowsubmissionsfromdate']
            ) {
                throw new \moodle_exception('invalidassigndates', 'tool_coursecompliance', '', $activityname);
            }
            if (!empty($dates['duedate']) && !empty($dates['cutoffdate']) && $dates['cutoffdate'] < $dates['duedate']) {
                throw new \moodle_exception('invalidassigncutoff', 'tool_coursecompliance', '', $activityname);
            }
            if (
                !empty($dates['duedate'])
                && !empty($dates['gradingduedate'])
                && $dates['gradingduedate'] < $dates['duedate']
            ) {
                throw new \moodle_exception('invalidassigngradingdue', 'tool_coursecompliance', '', $activityname);
            }
        } else if ($modname === 'forum') {
            if (!empty($dates['duedate']) && !empty($dates['cutoffdate']) && $dates['cutoffdate'] < $dates['duedate']) {
                throw new \moodle_exception('invalidforumcutoff', 'tool_coursecompliance', '', $activityname);
            }
        }
    }

    /**
     * Create one change record.
     *
     * @param \stdClass $activity Activity.
     * @param string $field Field name.
     * @param string $oldvalue Expected current value.
     * @param string $newvalue New value.
     * @return \stdClass
     */
    private function make_change(\stdClass $activity, string $field, string $oldvalue, string $newvalue): \stdClass {
        return (object)[
            'batchid' => 0,
            'cmid' => $activity->cmid,
            'courseid' => $activity->courseid,
            'modname' => $activity->modname,
            'instanceid' => $activity->instanceid,
            'fieldname' => $field,
            'oldvalue' => $oldvalue,
            'newvalue' => $newvalue,
            'status' => 'pending',
            'message' => '',
            'timecreated' => time(),
            'timeapplied' => 0,
        ];
    }

    /**
     * Human-readable operation summary.
     *
     * @param string $action Operation name.
     * @param array $options Operation options.
     * @return string
     */
    private function summary(string $action, array $options): string {
        if ($action === 'shift_dates') {
            return get_string('batchsummary_shift', 'tool_coursecompliance', (object)[
                'amount' => (int)($options['shiftamount'] ?? 0),
                'unit' => get_string('shiftunit_' . ($options['shiftunit'] ?? 'day'), 'tool_coursecompliance'),
            ]);
        }
        if ($action === 'visibility') {
            return get_string(
                'batchsummary_visibility',
                'tool_coursecompliance',
                get_string('visibility_' . ($options['visibilitystate'] ?? 'show'), 'tool_coursecompliance')
            );
        }
        if ($action === 'rename') {
            return get_string('batchsummary_rename', 'tool_coursecompliance', (object)[
                'find' => (string)($options['findtext'] ?? ''),
                'replace' => (string)($options['replacetext'] ?? ''),
            ]);
        }
        return get_string('batchsummary_dates', 'tool_coursecompliance');
    }
}
