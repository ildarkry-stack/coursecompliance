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

use core_course_category;
use tool_coursecompliance\local\rule\rule;

/**
 * Scans course categories against a compliance profile.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scanner {
    /** @var array<string, bool> Active exception lookup. */
    private array $exceptions = [];

    /**
     * Run a stored compliance scan.
     *
     * @param int $scanid Scan record id.
     * @param int $courselimit Maximum number of courses to inspect, 0 for all.
     */
    public function run(int $scanid, int $courselimit = 0): void {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $profileid = (int)$scan->profileid;
        $profile = $profileid > 0
            ? $DB->get_record('tool_coursecompliance_profile', ['id' => $profileid])
            : false;
        if (!$profile) {
            throw new \moodle_exception('scaninvalidprofile', 'tool_coursecompliance');
        }
        $config = profile_config::decode($profile->configjson);

        $scan->status = 'running';
        $scan->startedat = time();
        $DB->update_record('tool_coursecompliance_scan', $scan);
        $DB->delete_records('tool_coursecompliance_result', ['scanid' => $scanid]);
        $DB->delete_records('tool_coursecompliance_assessment', ['scanid' => $scanid]);
        $DB->delete_records('tool_coursecompliance_course', ['scanid' => $scanid]);

        $category = core_course_category::get($scan->categoryid, MUST_EXIST, true);
        $categoryids = array_merge([$category->id], $category->get_all_children_ids());
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $params['siteid'] = SITEID;
        $select = "category $insql AND id <> :siteid";
        $scope = json_decode((string)($scan->scopejson ?? ''), true);
        $scopecourseids = is_array($scope) && is_array($scope['courseids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $scope['courseids']))))
            : [];
        if ($scopecourseids) {
            [$scopeinsql, $scopeparams] = $DB->get_in_or_equal(
                $scopecourseids,
                SQL_PARAMS_NAMED,
                'scopecourse'
            );
            $select .= " AND id $scopeinsql";
            $params += $scopeparams;
            $courselimit = 0;
        } else if ($courselimit <= 0 && property_exists($scan, 'courselimit')) {
            $courselimit = (int)$scan->courselimit;
        }
        $courses = $DB->get_records_select(
            'course',
            $select,
            $params,
            'fullname ASC, id ASC',
            '*',
            0,
            max(0, $courselimit)
        );
        $courseids = array_keys($courses);

        $this->exceptions = $this->load_exceptions((int)$profile->id, $courseids);
        $mode = (string)($config['blueprintmode'] ?? 'manual');
        $manualenabled = $mode !== 'template';
        $blueprintenabled = $mode !== 'manual';
        $blueprint = is_array($config['blueprint'] ?? null) ? $config['blueprint'] : [];
        $visualblueprint = $blueprintenabled
            && !empty($blueprint)
            && (int)($blueprint['schema'] ?? 1) >= 2
            && !empty($config['blueprintrules']['enabled']);
        $manualassessmentenabled = $manualenabled && !$visualblueprint;
        $teachers = $manualenabled ? $this->load_teacher_counts($courseids) : [];
        $courseintegrity = $manualenabled ? $this->load_course_integrity($courseids) : [];
        $quizdata = ($manualassessmentenabled || $visualblueprint)
            ? $this->load_quizzes($courseids, $config)
            : [];
        $blueprintstates = [];
        if ($blueprintenabled && $blueprint) {
            $blueprintstates = blueprint_manager::load_states($courseids);
        }

        $totals = ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ignored' => 0];
        $assessmentcount = 0;

        foreach ($courses as $course) {
            $integrity = $courseintegrity[$course->id] ?? [];
            $coursecontext = [
                'course' => $course,
                'teachercount' => $teachers[$course->id] ?? 0,
                'orphanquizgradeitems' => (int)($integrity['orphanquizgradeitems'] ?? 0),
                'duplicatequizgradeitems' => (int)($integrity['duplicatequizgradeitems'] ?? 0),
                'completioncriteriacount' => (int)($integrity['completioncriteriacount'] ?? 0),
                'brokencompletioncriteria' => (int)($integrity['brokencompletioncriteria'] ?? 0),
            ];
            if ($manualenabled) {
                foreach (rule_registry::course_rules() as $rule) {
                    $outcome = $this->evaluate_and_store(
                        $rule,
                        $coursecontext,
                        $config,
                        $scanid,
                        (int)$course->id,
                        'course'
                    );
                    $this->increment_outcome($totals, $outcome);
                }
            }

            if ($blueprintstates) {
                $state = $blueprintstates[(int)$course->id] ?? [];
                $blueprintlocal = [
                    'exam' => ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ignored' => 0],
                    'resit1' => ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ignored' => 0],
                    'resit2' => ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ignored' => 0],
                ];
                foreach (blueprint_manager::compare($course, $state, $blueprint, $config) as $problem) {
                    $problemtype = (string)($problem['assessmenttype'] ?? 'course');
                    $outcome = $this->store_direct_violation(
                        $scanid,
                        (int)$course->id,
                        (string)$problem['component'],
                        (string)$problem['rulecode'],
                        (string)$problem['severity'],
                        $problemtype,
                        (int)$problem['objectid'],
                        (string)$problem['objectname'],
                        (string)$problem['message'],
                        (string)$problem['expected'],
                        (string)$problem['actual']
                    );
                    $this->increment_outcome($totals, $outcome);
                    if (isset($blueprintlocal[$problemtype])) {
                        $this->increment_outcome($blueprintlocal[$problemtype], $outcome);
                    }
                }

                if ($visualblueprint) {
                    foreach (['exam', 'resit1', 'resit2'] as $type) {
                        $assessmentrules = $config['blueprintrules']['assessments'][$type] ?? [];
                        $idnumber = (string)($config[$type . 'idnumber'] ?? $type);
                        $matches = $state['activities'][$idnumber] ?? [];
                        if (empty($assessmentrules['required']) && !$matches) {
                            continue;
                        }
                        $quiz = null;
                        $data = $quizdata[(int)$course->id][$type] ?? null;
                        if (count($matches) === 1 && (string)$matches[0]['modname'] === 'quiz' && $data) {
                            $quiz = $data['quiz'];
                            $assessmentcount++;
                            $integritycontext = $this->build_integrity_context(
                                $course,
                                $type,
                                $data,
                                $config,
                                true
                            );
                            foreach (rule_registry::quiz_integrity_rules() as $rule) {
                                $outcome = $this->evaluate_and_store(
                                    $rule,
                                    $integritycontext,
                                    $config,
                                    $scanid,
                                    (int)$course->id,
                                    $type
                                );
                                $this->increment_outcome($totals, $outcome);
                                $this->increment_outcome($blueprintlocal[$type], $outcome);
                            }
                        }
                        $this->store_assessment_state(
                            $scanid,
                            (int)$course->id,
                            $type,
                            $quiz,
                            $idnumber,
                            $blueprintlocal[$type]
                        );
                    }
                }
            }

            foreach ($this->compare_assessment_structure((int)$course->id, $quizdata, $config) as $problem) {
                $outcome = $this->store_direct_violation(
                    $scanid,
                    (int)$course->id,
                    'quiz',
                    (string)$problem['rulecode'],
                    'warning',
                    (string)$problem['assessmenttype'],
                    (int)$problem['objectid'],
                    (string)$problem['objectname'],
                    (string)$problem['message'],
                    (string)$problem['expected'],
                    (string)$problem['actual']
                );
                $this->increment_outcome($totals, $outcome);
            }

            if (!$manualassessmentenabled) {
                continue;
            }

            $bytype = $quizdata[$course->id] ?? [];
            foreach (['exam', 'resit1', 'resit2'] as $type) {
                $requiredkey = $type === 'exam' ? 'requireexam' : 'require' . $type;
                $required = !empty($config[$requiredkey]);
                $data = $bytype[$type] ?? null;
                if (!$required && $data === null) {
                    continue;
                }

                $local = ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ignored' => 0];
                if ($data === null) {
                    $rulecode = 'QUIZ_' . strtoupper($type) . '_REQUIRED';
                    $outcome = $this->store_direct_violation(
                        $scanid,
                        (int)$course->id,
                        'quiz',
                        $rulecode,
                        'critical',
                        $type,
                        0,
                        '',
                        get_string('v_quiz_required', 'tool_coursecompliance', $config[$type . 'idnumber'] ?? $type),
                        get_string('present', 'tool_coursecompliance'),
                        get_string('missing', 'tool_coursecompliance')
                    );
                    $this->increment_outcome($totals, $outcome);
                    $this->increment_outcome($local, $outcome);
                    $this->store_assessment_state(
                        $scanid,
                        (int)$course->id,
                        $type,
                        null,
                        $config[$type . 'idnumber'] ?? $type,
                        $local
                    );
                    continue;
                }

                $assessmentcount++;
                $quiz = $data['quiz'];
                $quizcontext = $this->build_integrity_context($course, $type, $data, $config, false);
                $quizcontext += [
                    'gradepass' => $data['gradepass'],
                    'gradeitemexists' => $data['gradeitemexists'],
                    'gradeitemmax' => $data['gradeitemmax'],
                    'gradeitemlocked' => $data['gradeitemlocked'],
                    'gradeitemhidden' => $data['gradeitemhidden'],
                    'overridecount' => $data['overridecount'],
                    'maxoverrideattempts' => $data['maxoverrideattempts'],
                    'maxoverridetimeclose' => $data['maxoverridetimeclose'],
                    'duplicatecount' => $data['duplicatecount'],
                ];
                foreach (array_merge(rule_registry::quiz_rules(), rule_registry::quiz_integrity_rules()) as $rule) {
                    $outcome = $this->evaluate_and_store(
                        $rule,
                        $quizcontext,
                        $config,
                        $scanid,
                        (int)$course->id,
                        $type
                    );
                    $this->increment_outcome($totals, $outcome);
                    $this->increment_outcome($local, $outcome);
                }
                $this->store_assessment_state(
                    $scanid,
                    (int)$course->id,
                    $type,
                    $quiz,
                    (string)$quiz->cmidnumber,
                    $local
                );
            }
        }

        $this->store_course_snapshots($scanid, $courseids);

        $scan->status = 'complete';
        $scan->courses = count($courses);
        $scan->assessments = $assessmentcount;
        $scan->critical = $totals['critical'];
        $scan->warnings = $totals['warning'];
        $scan->notices = $totals['notice'];
        $scan->ignored = $totals['ignored'];
        $scan->finishedat = time();
        $DB->update_record('tool_coursecompliance_scan', $scan);
    }

    /**
     * Evaluate one rule and save a violation when needed.
     *
     * @param rule $rule Rule.
     * @param array $context Evaluation context.
     * @param array $config Profile config.
     * @param int $scanid Scan id.
     * @param int $courseid Course id.
     * @param string $assessmenttype Assessment type.
     * @return string|null Severity, ignored or null.
     */
    private function evaluate_and_store(
        rule $rule,
        array $context,
        array $config,
        int $scanid,
        int $courseid,
        string $assessmenttype
    ): ?string {
        $violation = $rule->evaluate($context, $config);
        if ($violation === null) {
            return null;
        }
        if ($this->has_exception($courseid, $rule->get_code(), $rule->get_component(), $violation->objectid)) {
            return 'ignored';
        }
        $this->insert_result(
            $scanid,
            $courseid,
            $rule->get_component(),
            $rule->get_code(),
            $rule->get_severity(),
            $assessmenttype,
            $violation->objectid,
            $violation->objectname,
            $violation->message,
            $violation->expected,
            $violation->actual
        );
        return $rule->get_severity();
    }

    /**
     * Save a direct violation such as a missing required assessment.
     *
     * @param int $scanid Scan id.
     * @param int $courseid Course id.
     * @param string $component Audited component.
     * @param string $rulecode Stable rule code.
     * @param string $severity Violation severity.
     * @param string $assessmenttype Assessment period.
     * @param int $objectid Related object id.
     * @param string $objectname Related object name.
     * @param string $message Human-readable message.
     * @param string $expected Expected value.
     * @param string $actual Actual value.
     * @return string Severity or ignored.
     */
    private function store_direct_violation(
        int $scanid,
        int $courseid,
        string $component,
        string $rulecode,
        string $severity,
        string $assessmenttype,
        int $objectid,
        string $objectname,
        string $message,
        string $expected,
        string $actual
    ): string {
        if ($this->has_exception($courseid, $rulecode, $component, $objectid)) {
            return 'ignored';
        }
        $this->insert_result(
            $scanid,
            $courseid,
            $component,
            $rulecode,
            $severity,
            $assessmenttype,
            $objectid,
            $objectname,
            $message,
            $expected,
            $actual
        );
        return $severity;
    }

    /**
     * Insert one result row.
     *
     * @param int $scanid Scan id.
     * @param int $courseid Course id.
     * @param string $component Audited component.
     * @param string $rulecode Stable rule code.
     * @param string $severity Violation severity.
     * @param string $assessmenttype Assessment period.
     * @param int $objectid Related object id.
     * @param string $objectname Related object name.
     * @param string $message Human-readable message.
     * @param string $expected Expected value.
     * @param string $actual Actual value.
     */
    private function insert_result(
        int $scanid,
        int $courseid,
        string $component,
        string $rulecode,
        string $severity,
        string $assessmenttype,
        int $objectid,
        string $objectname,
        string $message,
        string $expected,
        string $actual
    ): void {
        global $DB;

        $DB->insert_record('tool_coursecompliance_result', (object)[
            'scanid' => $scanid,
            'courseid' => $courseid,
            'component' => $component,
            'assessmenttype' => $assessmenttype,
            'rulecode' => $rulecode,
            'severity' => $severity,
            'objectid' => $objectid,
            'objectname' => $objectname,
            'expected' => $expected,
            'actual' => $actual,
            'message' => $message,
            'timecreated' => time(),
        ]);
    }

    /**
     * Increment counters for an outcome.
     *
     * @param array $counters Counters.
     * @param string|null $outcome Outcome.
     */
    private function increment_outcome(array &$counters, ?string $outcome): void {
        if ($outcome !== null && array_key_exists($outcome, $counters)) {
            $counters[$outcome]++;
        }
    }

    /**
     * Save snapshot state for one assessment.
     *
     * @param int $scanid Scan id.
     * @param int $courseid Course id.
     * @param string $type Assessment type.
     * @param \stdClass|null $quiz Quiz record or null when missing.
     * @param string $idnumber Course-module idnumber.
     * @param array $local Local counters.
     */
    private function store_assessment_state(
        int $scanid,
        int $courseid,
        string $type,
        ?\stdClass $quiz,
        string $idnumber,
        array $local
    ): void {
        global $DB;

        $score = max(0, 100 - ($local['critical'] * 25) - ($local['warning'] * 8) - ($local['notice'] * 2));
        $DB->insert_record('tool_coursecompliance_assessment', (object)[
            'scanid' => $scanid,
            'courseid' => $courseid,
            'assessmenttype' => $type,
            'quizid' => $quiz ? (int)$quiz->id : 0,
            'objectname' => $quiz ? (string)$quiz->name : '',
            'idnumber' => $idnumber,
            'existsflag' => $quiz ? 1 : 0,
            'critical' => $local['critical'],
            'warnings' => $local['warning'],
            'notices' => $local['notice'],
            'ignored' => $local['ignored'],
            'score' => $score,
            'timecreated' => time(),
        ]);
    }

    /**
     * Store per-course readiness snapshots for dashboards and delta reports.
     *
     * @param int $scanid Scan id.
     * @param int[] $courseids Scanned course ids.
     */
    private function store_course_snapshots(int $scanid, array $courseids): void {
        global $DB;

        if (!$courseids) {
            return;
        }
        $counts = [];
        $records = $DB->get_records_sql(
            "SELECT courseid,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS critical,
                    SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warnings,
                    SUM(CASE WHEN severity = 'notice' THEN 1 ELSE 0 END) AS notices
               FROM {tool_coursecompliance_result}
              WHERE scanid = :scanid
           GROUP BY courseid",
            ['scanid' => $scanid]
        );
        foreach ($records as $record) {
            $counts[(int)$record->courseid] = $record;
        }
        $now = time();
        foreach ($courseids as $courseid) {
            $record = $counts[(int)$courseid] ?? null;
            $critical = $record ? (int)$record->critical : 0;
            $warnings = $record ? (int)$record->warnings : 0;
            $notices = $record ? (int)$record->notices : 0;
            $score = max(0, 100 - ($critical * 25) - ($warnings * 8) - ($notices * 2));
            $status = $critical > 0 ? 'critical' : ($warnings > 0 ? 'warning' : ($notices > 0 ? 'notice' : 'ready'));
            $DB->insert_record('tool_coursecompliance_course', (object)[
                'scanid' => $scanid,
                'courseid' => (int)$courseid,
                'critical' => $critical,
                'warnings' => $warnings,
                'notices' => $notices,
                'ignored' => 0,
                'score' => $score,
                'status' => $status,
                'timecreated' => $now,
            ]);
        }
    }

    /**
     * Load active exceptions for a profile and course set.
     *
     * @param int $profileid Profile id.
     * @param int[] $courseids Course ids.
     * @return array<string, bool>
     */
    private function load_exceptions(int $profileid, array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'exc');
        $params['profileid'] = $profileid;
        $params['now'] = time();
        $sql = "profileid = :profileid AND enabled = 1 AND courseid $insql
                AND (expiresat = 0 OR expiresat > :now)";
        $records = $DB->get_records_select('tool_coursecompliance_exception', $sql, $params);
        $lookup = [];
        foreach ($records as $record) {
            $key = $this->exception_key(
                (int)$record->courseid,
                $record->rulecode,
                $record->component,
                (int)$record->objectid
            );
            $lookup[$key] = true;
        }
        return $lookup;
    }

    /**
     * Determine whether a result is excepted.
     *
     * @param int $courseid Course id.
     * @param string $rulecode Stable rule code.
     * @param string $component Audited component.
     * @param int $objectid Related object id.
     * @return bool
     */
    private function has_exception(int $courseid, string $rulecode, string $component, int $objectid): bool {
        $exact = $this->exception_key($courseid, $rulecode, $component, $objectid);
        $generic = $this->exception_key($courseid, $rulecode, $component, 0);
        return isset($this->exceptions[$exact]) || ($objectid !== 0 && isset($this->exceptions[$generic]));
    }

    /**
     * Build exception lookup key.
     *
     * @param int $courseid Course id.
     * @param string $rulecode Stable rule code.
     * @param string $component Audited component.
     * @param int $objectid Related object id.
     * @return string
     */
    private function exception_key(int $courseid, string $rulecode, string $component, int $objectid): string {
        return $courseid . '|' . $component . '|' . $rulecode . '|' . $objectid;
    }

    /**
     * Load counts of directly assigned teacher archetypes per course.
     *
     * @param int[] $courseids Course ids.
     * @return array<int, int>
     */
    private function load_teacher_counts(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['ctxlevel'] = CONTEXT_COURSE;
        $sql = "SELECT c.instanceid AS courseid, COUNT(DISTINCT ra.userid) AS teachercount
                  FROM {context} c
                  JOIN {role_assignments} ra ON ra.contextid = c.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE c.contextlevel = :ctxlevel AND c.instanceid $insql
                   AND r.archetype IN ('teacher', 'editingteacher')
              GROUP BY c.instanceid";
        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $record) {
            $out[(int)$record->courseid] = (int)$record->teachercount;
        }
        return $out;
    }

    /**
     * Compare resit structure with the exam in the same course.
     *
     * @param int $courseid Course id.
     * @param array $quizdata Loaded Quiz data.
     * @param array $config Profile configuration.
     * @return array<int, array<string, mixed>>
     */
    private function compare_assessment_structure(int $courseid, array $quizdata, array $config): array {
        if (empty($config['compareassessmentstructure'])) {
            return [];
        }
        $exam = $quizdata[$courseid]['exam'] ?? null;
        if (!$exam) {
            return [];
        }
        $problems = [];
        foreach (['resit1', 'resit2'] as $type) {
            $candidate = $quizdata[$courseid][$type] ?? null;
            if (!$candidate) {
                continue;
            }
            $quiz = $candidate['quiz'];
            if ((int)$candidate['questioncount'] !== (int)$exam['questioncount']) {
                $problems[] = [
                    'rulecode' => 'QUIZ_STRUCTURE_DIFF_QUESTION_COUNT',
                    'assessmenttype' => $type,
                    'objectid' => (int)$quiz->id,
                    'objectname' => (string)$quiz->name,
                    'message' => get_string('v_quiz_structure_diff_question_count', 'tool_coursecompliance'),
                    'expected' => (string)$exam['questioncount'],
                    'actual' => (string)$candidate['questioncount'],
                ];
            }
            if (abs((float)$quiz->grade - (float)$exam['quiz']->grade) > 0.00001) {
                $problems[] = [
                    'rulecode' => 'QUIZ_STRUCTURE_DIFF_GRADE',
                    'assessmenttype' => $type,
                    'objectid' => (int)$quiz->id,
                    'objectname' => (string)$quiz->name,
                    'message' => get_string('v_quiz_structure_diff_grade', 'tool_coursecompliance'),
                    'expected' => (string)$exam['quiz']->grade,
                    'actual' => (string)$quiz->grade,
                ];
            }
            $examcategories = $exam['integrity']['bankcategories'] ?? [];
            $candidatecategories = $candidate['integrity']['bankcategories'] ?? [];
            sort($examcategories);
            sort($candidatecategories);
            if ($examcategories && $candidatecategories && $examcategories !== $candidatecategories) {
                $problems[] = [
                    'rulecode' => 'QUIZ_STRUCTURE_DIFF_BANK_CATEGORY',
                    'assessmenttype' => $type,
                    'objectid' => (int)$quiz->id,
                    'objectname' => (string)$quiz->name,
                    'message' => get_string('v_quiz_structure_diff_bank_category', 'tool_coursecompliance'),
                    'expected' => implode(', ', $examcategories),
                    'actual' => implode(', ', $candidatecategories),
                ];
            }
        }
        return $problems;
    }

    /**
     * Load gradebook and course-completion integrity counters.
     *
     * @param int[] $courseids Course ids.
     * @return array<int, array<string, int>>
     */
    private function load_course_integrity(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        $out = [];
        foreach ($courseids as $courseid) {
            $out[(int)$courseid] = [
                'orphanquizgradeitems' => 0,
                'duplicatequizgradeitems' => 0,
                'completioncriteriacount' => 0,
                'brokencompletioncriteria' => 0,
            ];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'integritycourse');

        $orphans = $DB->get_records_sql(
            "SELECT gi.courseid, COUNT(1) AS issuecount
               FROM {grade_items} gi
          LEFT JOIN {quiz} q ON q.id = gi.iteminstance AND q.course = gi.courseid
              WHERE gi.courseid $insql
                AND gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND q.id IS NULL
           GROUP BY gi.courseid",
            $params
        );
        foreach ($orphans as $record) {
            $out[(int)$record->courseid]['orphanquizgradeitems'] = (int)$record->issuecount;
        }

        $duplicates = $DB->get_records_sql(
            "SELECT x.courseid, COUNT(1) AS issuecount
               FROM (
                     SELECT courseid, iteminstance
                       FROM {grade_items}
                      WHERE courseid $insql
                        AND itemtype = 'mod'
                        AND itemmodule = 'quiz'
                   GROUP BY courseid, iteminstance
                     HAVING COUNT(1) > 1
                    ) x
           GROUP BY x.courseid",
            $params
        );
        foreach ($duplicates as $record) {
            $out[(int)$record->courseid]['duplicatequizgradeitems'] = (int)$record->issuecount;
        }

        $criteriacounts = $DB->get_records_sql(
            "SELECT course AS courseid, COUNT(1) AS criteriacount
               FROM {course_completion_criteria}
              WHERE course $insql
           GROUP BY course",
            $params
        );
        foreach ($criteriacounts as $record) {
            $out[(int)$record->courseid]['completioncriteriacount'] = (int)$record->criteriacount;
        }

        $brokencriteria = $DB->get_records_sql(
            "SELECT ccc.course AS courseid, COUNT(1) AS issuecount
               FROM {course_completion_criteria} ccc
          LEFT JOIN {course_modules} cm ON cm.id = ccc.moduleinstance AND cm.course = ccc.course
              WHERE ccc.course $insql
                AND ccc.moduleinstance > 0
                AND cm.id IS NULL
           GROUP BY ccc.course",
            $params
        );
        foreach ($brokencriteria as $record) {
            $out[(int)$record->courseid]['brokencompletioncriteria'] = (int)$record->issuecount;
        }
        return $out;
    }

    /**
     * Build a common context for Quiz question-bank integrity rules.
     *
     * @param \stdClass $course Course record.
     * @param string $type Assessment type.
     * @param array $data Loaded Quiz data.
     * @param array $config Profile configuration.
     * @param bool $visualblueprint Whether visual Blueprint rules are active.
     * @return array<string, mixed>
     */
    private function build_integrity_context(
        \stdClass $course,
        string $type,
        array $data,
        array $config,
        bool $visualblueprint
    ): array {
        $rules = $visualblueprint
            ? ($config['blueprintrules']['assessments'][$type] ?? [])
            : [];
        $integrity = is_array($data['integrity'] ?? null) ? $data['integrity'] : [];
        return [
            'course' => $course,
            'quiz' => $data['quiz'],
            'assessmenttype' => $type,
            'visualblueprint' => $visualblueprint,
            'questioncount' => (int)($data['questioncount'] ?? 0),
            'zeromarkcount' => (int)($data['zeromarkcount'] ?? 0),
            'structureerror' => (string)($integrity['structureerror'] ?? ''),
            'slotmarksum' => (float)($integrity['slotmarksum'] ?? 0),
            'bankquestioncount' => (int)($integrity['bankquestioncount'] ?? 0),
            'bankcategorycount' => (int)($integrity['bankcategorycount'] ?? 0),
            'bankcategories' => $integrity['bankcategories'] ?? [],
            'categoryquestioncount' => (int)($integrity['categoryquestioncount'] ?? 0),
            'unusedbankquestioncount' => (int)($integrity['unusedbankquestioncount'] ?? 0),
            'duplicatecontentcount' => (int)($integrity['duplicatecontentcount'] ?? 0),
            'draftquestioncount' => (int)($integrity['draftquestioncount'] ?? 0),
            'brokenquestioncount' => (int)($integrity['brokenquestioncount'] ?? 0),
            'nocorrectanswercount' => (int)($integrity['nocorrectanswercount'] ?? 0),
            'correctansweruncheckedcount' => (int)($integrity['correctansweruncheckedcount'] ?? 0),
            'emptyrandompoolcount' => (int)($integrity['emptyrandompoolcount'] ?? 0),
            'insufficientrandompoolcount' => (int)($integrity['insufficientrandompoolcount'] ?? 0),
            'deepchecktruncated' => !empty($integrity['deepchecktruncated']),
            'checkquestionintegrity' => $visualblueprint
                ? !empty($rules['question_integrity'])
                : !empty($config['checkquestionintegrity']),
            'requirequestionready' => $visualblueprint
                ? !empty($rules['question_ready_only'])
                : !empty($config['requirequestionready']),
            'requirecorrectanswers' => $visualblueprint
                ? !empty($rules['question_correct_answer'])
                : !empty($config['requirecorrectanswers']),
            'requiremarksmatch' => $visualblueprint
                ? !empty($rules['marks_match_grade'])
                : !empty($config['requiremarksmatch']),
            'requiresinglebankcategory' => $visualblueprint
                ? !empty($rules['single_bank_category'])
                : !empty($config['requiresinglebankcategory']),
        ];
    }

    /**
     * Load configured exam/resit quizzes with assessment summary data.
     *
     * @param int[] $courseids Course ids.
     * @param array $config Profile configuration.
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function load_quizzes(array $courseids, array $config): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        $idnumbers = array_values(array_unique(array_filter([
            $config['examidnumber'] ?? 'exam',
            $config['resit1idnumber'] ?? 'resit1',
            $config['resit2idnumber'] ?? 'resit2',
        ], static fn ($value): bool => trim((string)$value) !== '')));
        if (!$idnumbers) {
            return [];
        }

        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'qcourse');
        [$idinsql, $idparams] = $DB->get_in_or_equal($idnumbers, SQL_PARAMS_NAMED, 'idnum');
        $params = $courseparams + $idparams;
        $sql = "SELECT q.*, cm.id AS cmid, cm.idnumber AS cmidnumber,
                       COALESCE(qs.questioncount, 0) AS questioncount,
                       COALESCE(qs.zeromarkcount, 0) AS zeromarkcount,
                       gi.id AS gradeitemid,
                       COALESCE(gi.gradepass, 0) AS gradepass,
                       COALESCE(gi.grademax, 0) AS gradeitemmax,
                       COALESCE(gi.locked, 0) AS gradeitemlocked,
                       COALESCE(gi.hidden, 0) AS gradeitemhidden
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.course = q.course AND cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             LEFT JOIN (
                           SELECT quizid, COUNT(1) AS questioncount,
                                  SUM(CASE WHEN maxmark <= 0 THEN 1 ELSE 0 END) AS zeromarkcount
                             FROM {quiz_slots}
                         GROUP BY quizid
                       ) qs ON qs.quizid = q.id
             LEFT JOIN {grade_items} gi ON gi.courseid = q.course
                       AND gi.itemmodule = 'quiz'
                       AND gi.iteminstance = q.id
                       AND gi.itemnumber = 0
                 WHERE q.course $courseinsql AND cm.idnumber $idinsql
              ORDER BY q.course, cm.idnumber, q.id";
        $records = $DB->get_records_sql($sql, $params);

        $mapping = [
            (string)($config['examidnumber'] ?? 'exam') => 'exam',
            (string)($config['resit1idnumber'] ?? 'resit1') => 'resit1',
            (string)($config['resit2idnumber'] ?? 'resit2') => 'resit2',
        ];
        $grouped = [];
        $quizids = [];
        foreach ($records as $quiz) {
            $type = $mapping[$quiz->cmidnumber] ?? null;
            if ($type === null) {
                continue;
            }
            $courseid = (int)$quiz->course;
            if (!isset($grouped[$courseid][$type])) {
                $grouped[$courseid][$type] = [];
            }
            $grouped[$courseid][$type][] = $quiz;
            $quizids[] = (int)$quiz->id;
        }
        $overrides = $this->load_override_summary($quizids);

        $out = [];
        foreach ($grouped as $courseid => $types) {
            foreach ($types as $type => $items) {
                $quiz = reset($items);
                $override = $overrides[(int)$quiz->id] ?? [
                    'count' => 0,
                    'maxattempts' => 0,
                    'maxtimeclose' => 0,
                ];
                $integrity = quiz_integrity_inspector::inspect($quiz);
                $out[$courseid][$type] = [
                    'quiz' => $quiz,
                    'questioncount' => (int)$quiz->questioncount,
                    'zeromarkcount' => (int)$quiz->zeromarkcount,
                    'gradepass' => (float)$quiz->gradepass,
                    'gradeitemexists' => !empty($quiz->gradeitemid),
                    'gradeitemmax' => (float)$quiz->gradeitemmax,
                    'gradeitemlocked' => !empty($quiz->gradeitemlocked),
                    'gradeitemhidden' => !empty($quiz->gradeitemhidden),
                    'overridecount' => $override['count'],
                    'maxoverrideattempts' => $override['maxattempts'],
                    'maxoverridetimeclose' => $override['maxtimeclose'],
                    'duplicatecount' => count($items),
                    'integrity' => $integrity,
                ];
            }
        }
        return $out;
    }

    /**
     * Aggregate override data by quiz.
     *
     * @param int[] $quizids Quiz ids.
     * @return array<int, array{count:int,maxattempts:int,maxtimeclose:int}>
     */
    private function load_override_summary(array $quizids): array {
        global $DB;

        if (!$quizids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values(array_unique($quizids)), SQL_PARAMS_NAMED, 'overridequiz');
        $sql = "SELECT quiz, COUNT(1) AS overridecount,
                       MAX(COALESCE(attempts, 0)) AS maxattempts,
                       MAX(COALESCE(timeclose, 0)) AS maxtimeclose
                  FROM {quiz_overrides}
                 WHERE quiz $insql
              GROUP BY quiz";
        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $record) {
            $out[(int)$record->quiz] = [
                'count' => (int)$record->overridecount,
                'maxattempts' => (int)$record->maxattempts,
                'maxtimeclose' => (int)$record->maxtimeclose,
            ];
        }
        return $out;
    }
}
