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
     */
    public function run(int $scanid): void {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        $profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
        $config = profile_config::decode($profile->configjson);

        $scan->status = 'running';
        $scan->startedat = time();
        $DB->update_record('tool_coursecompliance_scan', $scan);
        $DB->delete_records('tool_coursecompliance_result', ['scanid' => $scanid]);
        $DB->delete_records('tool_coursecompliance_assessment', ['scanid' => $scanid]);

        $category = core_course_category::get($scan->categoryid, MUST_EXIST, true);
        $categoryids = array_merge([$category->id], $category->get_all_children_ids());
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $params['siteid'] = SITEID;
        $courses = $DB->get_records_select('course', "category $insql AND id <> :siteid", $params, 'id ASC');
        $courseids = array_keys($courses);

        $this->exceptions = $this->load_exceptions((int)$profile->id, $courseids);
        $teachers = $this->load_teacher_counts($courseids);
        $quizdata = $this->load_quizzes($courseids, $config);

        $totals = ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ignored' => 0];
        $assessmentcount = 0;

        foreach ($courses as $course) {
            $coursecontext = [
                'course' => $course,
                'teachercount' => $teachers[$course->id] ?? 0,
            ];
            foreach (rule_registry::course_rules() as $rule) {
                $outcome = $this->evaluate_and_store($rule, $coursecontext, $config, $scanid, (int)$course->id, 'course');
                $this->increment_outcome($totals, $outcome);
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
                $quizcontext = [
                    'course' => $course,
                    'quiz' => $quiz,
                    'assessmenttype' => $type,
                    'questioncount' => $data['questioncount'],
                    'zeromarkcount' => $data['zeromarkcount'],
                    'gradepass' => $data['gradepass'],
                    'gradeitemexists' => $data['gradeitemexists'],
                    'gradeitemmax' => $data['gradeitemmax'],
                    'gradeitemlocked' => $data['gradeitemlocked'],
                    'overridecount' => $data['overridecount'],
                    'maxoverrideattempts' => $data['maxoverrideattempts'],
                    'maxoverridetimeclose' => $data['maxoverridetimeclose'],
                    'duplicatecount' => $data['duplicatecount'],
                ];
                foreach (rule_registry::quiz_rules() as $rule) {
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
        $sql = "SELECT q.*, cm.idnumber AS cmidnumber,
                       COALESCE(qs.questioncount, 0) AS questioncount,
                       COALESCE(qs.zeromarkcount, 0) AS zeromarkcount,
                       gi.id AS gradeitemid,
                       COALESCE(gi.gradepass, 0) AS gradepass,
                       COALESCE(gi.grademax, 0) AS gradeitemmax,
                       COALESCE(gi.locked, 0) AS gradeitemlocked
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
                $out[$courseid][$type] = [
                    'quiz' => $quiz,
                    'questioncount' => (int)$quiz->questioncount,
                    'zeromarkcount' => (int)$quiz->zeromarkcount,
                    'gradepass' => (float)$quiz->gradepass,
                    'gradeitemexists' => !empty($quiz->gradeitemid),
                    'gradeitemmax' => (float)$quiz->gradeitemmax,
                    'gradeitemlocked' => !empty($quiz->gradeitemlocked),
                    'overridecount' => $override['count'],
                    'maxoverrideattempts' => $override['maxattempts'],
                    'maxoverridetimeclose' => $override['maxtimeclose'],
                    'duplicatecount' => count($items),
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
