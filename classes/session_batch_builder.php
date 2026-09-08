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
 * Builds a safe multi-course session schedule preview.
 *
 * The planner never writes to Moodle activities directly. It resolves exam/resit
 * targets into the same immutable change records used by Activity Operations, so
 * conflict detection, background execution and safe rollback stay centralized.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_batch_builder {
    /** @var string[] Supported periods. */
    private const PERIODS = ['exam', 'resit1', 'resit2'];

    /**
     * Create a session schedule preview.
     *
     * @param \stdClass $data Validated form data.
     * @param int $userid User creating the preview.
     * @return int Batch id.
     */
    public function create(\stdClass $data, int $userid): int {
        global $DB;

        $categoryid = (int)$data->categoryid;
        $categorycontext = \context_coursecat::instance($categoryid);
        require_capability('tool/coursecompliance:scheduleassessments', $categorycontext);

        $periods = $this->normalise_periods($data);
        if (!$periods) {
            throw new \moodle_exception('selectperiodrequired', 'tool_coursecompliance');
        }
        $seen = ['quiz' => [], 'assign' => []];
        foreach ($periods as $period => $config) {
            foreach (['quiz', 'assign'] as $modname) {
                $idnumber = $config[$modname . 'idnumber'];
                if ($idnumber === '') {
                    continue;
                }
                if (isset($seen[$modname][$idnumber])) {
                    throw new \moodle_exception('duplicatesessiontargetconfig', 'tool_coursecompliance', '', $idnumber);
                }
                $seen[$modname][$idnumber] = $period;
            }
        }

        $category = \core_course_category::get($categoryid, MUST_EXIST, true);
        $categoryids = [$categoryid];
        if (!empty($data->includesubcategories)) {
            $categoryids = array_merge($categoryids, $category->get_all_children_ids());
        }
        [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'sessioncat');
        $courses = $DB->get_records_select(
            'course',
            "category $catsql AND id <> :siteid",
            $catparams + ['siteid' => SITEID],
            'fullname ASC',
            'id,fullname,shortname,category'
        );
        if (!$courses) {
            throw new \moodle_exception('nocoursesinscope', 'tool_coursecompliance');
        }

        $targets = $this->target_idnumbers($periods);
        $matches = $this->preload_matches(array_keys($courses), $targets);
        $adjustassignmentdates = !empty($data->adjustassignmentdates);

        $filterpayload = [
            'source' => 'session_schedule',
            'includesubcategories' => !empty($data->includesubcategories),
            'adjustassignmentdates' => $adjustassignmentdates,
            'periods' => $periods,
        ];

        $transaction = $DB->start_delegated_transaction();
        $batchid = $DB->insert_record('tool_cc_act_batch', (object)[
            'userid' => $userid,
            'categoryid' => $categoryid,
            'sourcebatchid' => 0,
            'action' => 'session_schedule',
            'status' => 'preview',
            'filterjson' => json_encode($filterpayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary' => get_string('batchsummary_session', 'tool_coursecompliance', count($courses)),
            'totalchanges' => 0,
            'applied' => 0,
            'skipped' => 0,
            'failed' => 0,
            'timecreated' => time(),
            'timestarted' => 0,
            'timefinished' => 0,
        ]);

        $totalchanges = 0;
        foreach ($courses as $course) {
            foreach ($periods as $period => $config) {
                foreach (['quiz', 'assign'] as $modname) {
                    $idnumber = $config[$modname . 'idnumber'];
                    if ($idnumber === '') {
                        continue;
                    }
                    $found = $matches[(int)$course->id][$modname][$idnumber] ?? [];
                    if (!$found) {
                        $this->insert_issue($batchid, $course, $period, $modname, $idnumber, 'missing', '');
                        continue;
                    }
                    if (count($found) > 1) {
                        $this->insert_issue($batchid, $course, $period, $modname, $idnumber, 'duplicate', (string)count($found));
                        continue;
                    }

                    $activity = reset($found);
                    $context = \context_module::instance((int)$activity->cmid);
                    if (!has_capability('moodle/course:manageactivities', $context, $userid)) {
                        $this->insert_issue($batchid, $course, $period, $modname, $idnumber, 'nopermission', '');
                        continue;
                    }

                    $fieldchanges = $this->build_field_changes(
                        $activity,
                        (int)$config['open'],
                        (int)$config['close'],
                        $adjustassignmentdates
                    );
                    foreach ($fieldchanges as $field => [$oldvalue, $newvalue]) {
                        $DB->insert_record('tool_cc_act_change', (object)[
                            'batchid' => $batchid,
                            'cmid' => (int)$activity->cmid,
                            'courseid' => (int)$course->id,
                            'modname' => $modname,
                            'instanceid' => (int)$activity->instanceid,
                            'fieldname' => $field,
                            'oldvalue' => (string)$oldvalue,
                            'newvalue' => (string)$newvalue,
                            'status' => 'pending',
                            'message' => '',
                            'timecreated' => time(),
                            'timeapplied' => 0,
                        ]);
                        $totalchanges++;
                    }
                }
            }
        }

        $DB->set_field('tool_cc_act_batch', 'totalchanges', $totalchanges, ['id' => $batchid]);
        $transaction->allow_commit();
        return $batchid;
    }

    /**
     * Normalize selected periods from form data.
     *
     * @param \stdClass $data Form data.
     * @return array
     */
    private function normalise_periods(\stdClass $data): array {
        $periods = [];
        foreach (self::PERIODS as $period) {
            if (empty($data->{'update_' . $period})) {
                continue;
            }
            $open = (int)$data->{$period . '_open'};
            $close = (int)$data->{$period . '_close'};
            if ($close <= $open) {
                throw new \moodle_exception('invalidperioddates', 'tool_coursecompliance');
            }
            $quizidnumber = trim((string)$data->{$period . '_quizidnumber'});
            $assignidnumber = trim((string)$data->{$period . '_assignidnumber'});
            if ($quizidnumber === '' && $assignidnumber === '') {
                throw new \moodle_exception('sessiontargetrequired', 'tool_coursecompliance');
            }
            $periods[$period] = [
                'open' => $open,
                'close' => $close,
                'quizidnumber' => $quizidnumber,
                'assignidnumber' => $assignidnumber,
            ];
        }
        return $periods;
    }

    /**
     * Collect target idnumbers by module type.
     *
     * @param array $periods Normalized periods.
     * @return array
     */
    private function target_idnumbers(array $periods): array {
        $targets = ['quiz' => [], 'assign' => []];
        foreach ($periods as $config) {
            foreach (['quiz', 'assign'] as $modname) {
                $idnumber = $config[$modname . 'idnumber'];
                if ($idnumber !== '') {
                    $targets[$modname][$idnumber] = $idnumber;
                }
            }
        }
        return [
            'quiz' => array_values($targets['quiz']),
            'assign' => array_values($targets['assign']),
        ];
    }

    /**
     * Preload all matching Quiz and Assignment instances.
     *
     * @param int[] $courseids Course ids.
     * @param array $targets Idnumbers by module.
     * @return array
     */
    private function preload_matches(array $courseids, array $targets): array {
        global $DB;

        $result = [];
        foreach (['quiz', 'assign'] as $modname) {
            if (empty($targets[$modname])) {
                continue;
            }
            foreach (array_chunk($courseids, 500) as $coursechunk) {
                [$courseinsql, $courseparams] = $DB->get_in_or_equal($coursechunk, SQL_PARAMS_NAMED, $modname . 'course');
                [$idinsql, $idparams] = $DB->get_in_or_equal($targets[$modname], SQL_PARAMS_NAMED, $modname . 'idnum');
                $fields = $modname === 'quiz'
                    ? 'q.id AS instanceid, q.name, q.timeopen, q.timeclose'
                    : 'a.id AS instanceid, a.name, a.allowsubmissionsfromdate, a.duedate, a.cutoffdate, a.gradingduedate';
                $alias = $modname === 'quiz' ? 'q' : 'a';
                $sql = "SELECT cm.id AS cmid, cm.course AS courseid, cm.idnumber, {$fields}
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                          JOIN {{$modname}} {$alias} ON {$alias}.id = cm.instance AND {$alias}.course = cm.course
                         WHERE cm.course $courseinsql
                           AND cm.idnumber $idinsql
                           AND cm.deletioninprogress = 0
                      ORDER BY cm.id ASC";
                $params = array_merge($courseparams, $idparams, ['modulename' => $modname]);
                foreach ($DB->get_records_sql($sql, $params) as $record) {
                    $result[(int)$record->courseid][$modname][(string)$record->idnumber][] = $record;
                }
            }
        }
        return $result;
    }

    /**
     * Build changed fields for one target activity.
     *
     * @param \stdClass $activity Raw activity record.
     * @param int $newopen New opening/start timestamp.
     * @param int $newclose New close/due timestamp.
     * @param bool $adjustassignmentdates Keep enabled secondary Assignment dates valid.
     * @return array<string, array{0:int,1:int}>
     */
    private function build_field_changes(\stdClass $activity, int $newopen, int $newclose, bool $adjustassignmentdates): array {
        $changes = [];
        if (property_exists($activity, 'timeopen')) {
            $newvalues = ['timeopen' => $newopen, 'timeclose' => $newclose];
        } else {
            $newvalues = [
                'allowsubmissionsfromdate' => $newopen,
                'duedate' => $newclose,
                'cutoffdate' => (int)$activity->cutoffdate,
                'gradingduedate' => (int)$activity->gradingduedate,
            ];
            if ($adjustassignmentdates) {
                if ($newvalues['cutoffdate'] > 0 && $newvalues['cutoffdate'] < $newclose) {
                    $newvalues['cutoffdate'] = $newclose;
                }
                if ($newvalues['gradingduedate'] > 0 && $newvalues['gradingduedate'] < $newclose) {
                    $newvalues['gradingduedate'] = $newclose;
                }
            }
        }

        foreach ($newvalues as $field => $newvalue) {
            $oldvalue = (int)$activity->{$field};
            if ($oldvalue !== (int)$newvalue) {
                $changes[$field] = [$oldvalue, (int)$newvalue];
            }
        }
        return $changes;
    }

    /**
     * Record a target that cannot safely become a change.
     *
     * @param int $batchid Batch id.
     * @param \stdClass $course Course record.
     * @param string $period Assessment period.
     * @param string $modname Module name.
     * @param string $idnumber Target idnumber.
     * @param string $issuecode Issue code.
     * @param string $details Optional details.
     */
    private function insert_issue(
        int $batchid,
        \stdClass $course,
        string $period,
        string $modname,
        string $idnumber,
        string $issuecode,
        string $details
    ): void {
        global $DB;
        $DB->insert_record('tool_cc_session_issue', (object)[
            'batchid' => $batchid,
            'courseid' => (int)$course->id,
            'assessmenttype' => $period,
            'modname' => $modname,
            'idnumber' => $idnumber,
            'issuecode' => $issuecode,
            'details' => $details,
            'timecreated' => time(),
        ]);
    }
}
