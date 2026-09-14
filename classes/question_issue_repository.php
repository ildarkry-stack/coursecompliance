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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Detailed question-bank issue helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Access and presentation helpers for per-question audit details.
 */
final class question_issue_repository {
    /** Aggregate rule to detailed issue-code mapping. */
    private const RULE_ISSUES = [
        'QUIZ_BANK_DUPLICATE_CONTENT' => ['duplicate_content'],
        'QUIZ_BANK_UNUSED_QUESTIONS' => ['unused_question'],
        'QUIZ_BROKEN_QUESTIONS' => ['broken_question', 'random_pool_broken'],
        'QUIZ_CORRECT_ANSWER_CHECK_PARTIAL' => ['correct_answer_unchecked'],
        'QUIZ_QUESTION_CORRECT_ANSWER_REQUIRED' => ['no_correct_answer'],
        'QUIZ_QUESTION_READY_ONLY' => ['question_not_ready'],
        'QUIZ_RANDOM_POOL_EMPTY' => ['random_pool_empty'],
        'QUIZ_RANDOM_POOL_INSUFFICIENT' => ['random_pool_insufficient'],
        'QUIZ_STRUCTURE_LOADABLE' => ['structure_error'],
        'QUIZ_ZERO_MARK_QUESTIONS' => ['zero_mark'],
    ];

    /**
     * Whether an aggregate scan result has stored question-level details.
     *
     * @param string $rulecode Rule code.
     * @return bool
     */
    public static function supports_rule(string $rulecode): bool {
        return isset(self::RULE_ISSUES[$rulecode]);
    }

    /**
     * Detailed issue codes for an aggregate rule.
     *
     * @param string $rulecode Rule code.
     * @return string[]
     */
    public static function issue_codes_for_rule(string $rulecode): array {
        return self::RULE_ISSUES[$rulecode] ?? [];
    }

    /**
     * Count details stored for an aggregate result.
     *
     * @param \stdClass $result Scan result.
     * @return int
     */
    public static function count_for_result(\stdClass $result): int {
        global $DB;

        $codes = self::issue_codes_for_rule((string)$result->rulecode);
        if (!$codes) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($codes, SQL_PARAMS_NAMED, 'qissuecode');
        $params['scanid'] = (int)$result->scanid;
        $params['courseid'] = (int)$result->courseid;
        $params['assessmenttype'] = (string)$result->assessmenttype;
        return (int)$DB->count_records_select(
            'tool_coursecompliance_qissue',
            "scanid = :scanid AND courseid = :courseid AND assessmenttype = :assessmenttype " .
                "AND issuecode $insql",
            $params
        );
    }

    /**
     * Count detailed issue rows for many aggregate results in one query.
     *
     * @param array $results Aggregate results.
     * @return array<int, int> Counts keyed by result id.
     */
    public static function counts_for_results(array $results): array {
        global $DB;

        $lookup = [];
        $scanids = [];
        $counts = [];
        foreach ($results as $result) {
            $resultid = (int)$result->id;
            $counts[$resultid] = 0;
            foreach (self::issue_codes_for_rule((string)$result->rulecode) as $code) {
                $key = self::detail_key(
                    (int)$result->scanid,
                    (int)$result->courseid,
                    (string)$result->assessmenttype,
                    $code
                );
                $lookup[$key][] = $resultid;
                $scanids[(int)$result->scanid] = true;
            }
        }
        if (!$scanids) {
            return $counts;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($scanids), SQL_PARAMS_NAMED, 'detailscan');
        $records = $DB->get_records_select(
            'tool_coursecompliance_qissue',
            "scanid $insql",
            $params,
            '',
            'id, scanid, courseid, assessmenttype, issuecode'
        );
        foreach ($records as $record) {
            $key = self::detail_key(
                (int)$record->scanid,
                (int)$record->courseid,
                (string)$record->assessmenttype,
                (string)$record->issuecode
            );
            foreach ($lookup[$key] ?? [] as $resultid) {
                $counts[$resultid]++;
            }
        }
        return $counts;
    }

    /**
     * URL to detailed issues for an aggregate result.
     *
     * @param \stdClass $result Scan result.
     * @return \moodle_url
     */
    public static function url_for_result(\stdClass $result): \moodle_url {
        return new \moodle_url('/admin/tool/coursecompliance/question_issues.php', [
            'resultid' => (int)$result->id,
        ]);
    }

    /**
     * Load detailed issue rows for one aggregate result.
     *
     * @param \stdClass $result Scan result.
     * @return \stdClass[]
     */
    public static function for_result(\stdClass $result): array {
        global $DB;

        $codes = self::issue_codes_for_rule((string)$result->rulecode);
        if (!$codes) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($codes, SQL_PARAMS_NAMED, 'qissuecode');
        $params['scanid'] = (int)$result->scanid;
        $params['courseid'] = (int)$result->courseid;
        $params['assessmenttype'] = (string)$result->assessmenttype;
        $sql = "SELECT qi.*, c.fullname, c.shortname, q.name AS quizname
                  FROM {tool_coursecompliance_qissue} qi
                  JOIN {course} c ON c.id = qi.courseid
             LEFT JOIN {quiz} q ON q.id = qi.quizid
                 WHERE qi.scanid = :scanid
                   AND qi.courseid = :courseid
                   AND qi.assessmenttype = :assessmenttype
                   AND qi.issuecode $insql
              ORDER BY CASE qi.severity
                           WHEN 'critical' THEN 1
                           WHEN 'warning' THEN 2
                           ELSE 3
                       END,
                       qi.categoryname,
                       qi.questionname,
                       qi.id";
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Return current question-bank issues from the same scans used by Action Center.
     *
     * @param int $limit Maximum rows.
     * @return \stdClass[]
     */
    public static function current_issues(int $limit = 5000): array {
        global $DB;

        $results = scan_analysis::current_action_results(10000);
        $allowed = [];
        $scanids = [];
        foreach ($results as $result) {
            $codes = self::issue_codes_for_rule((string)$result->rulecode);
            if (!$codes) {
                continue;
            }
            $scanid = (int)$result->scanid;
            $scanids[$scanid] = true;
            foreach ($codes as $code) {
                $allowed[self::detail_key($scanid, (int)$result->courseid, (string)$result->assessmenttype, $code)] = true;
            }
        }
        if (!$scanids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($scanids), SQL_PARAMS_NAMED, 'currentscan');
        $allcodes = [];
        foreach (array_keys($allowed) as $key) {
            $parts = explode('|', $key);
            $allcodes[$parts[3] ?? ''] = true;
        }
        unset($allcodes['']);
        [$codesql, $codeparams] = $DB->get_in_or_equal(array_keys($allcodes), SQL_PARAMS_NAMED, 'currentissue');
        $params += $codeparams;
        $sql = "SELECT qi.*, c.fullname, c.shortname, q.name AS quizname
                  FROM {tool_coursecompliance_qissue} qi
                  JOIN {course} c ON c.id = qi.courseid
             LEFT JOIN {quiz} q ON q.id = qi.quizid
                 WHERE qi.scanid $insql
                   AND qi.issuecode $codesql
              ORDER BY qi.id DESC";
        $records = $DB->get_records_sql($sql, $params);
        $out = [];
        foreach ($records as $record) {
            $key = self::detail_key(
                (int)$record->scanid,
                (int)$record->courseid,
                (string)$record->assessmenttype,
                (string)$record->issuecode
            );
            if (isset($allowed[$key])) {
                $out[] = $record;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Human-readable issue label.
     *
     * @param string $issuecode Issue code.
     * @return string
     */
    public static function issue_label(string $issuecode): string {
        $key = 'qissue_' . $issuecode;
        if (get_string_manager()->string_exists($key, 'tool_coursecompliance')) {
            return get_string($key, 'tool_coursecompliance');
        }
        return $issuecode;
    }

    /**
     * Human-readable details from the stored JSON payload.
     *
     * @param \stdClass $issue Detailed issue.
     * @return string
     */
    public static function details_text(\stdClass $issue): string {
        $details = json_decode((string)($issue->details ?? ''), true);
        if (!is_array($details) || !$details) {
            return '';
        }
        return match ((string)$issue->issuecode) {
            'duplicate_content' => !empty($details['duplicateof'])
                ? get_string('qissue_duplicateof', 'tool_coursecompliance', (int)$details['duplicateof'])
                : '',
            'random_pool_empty',
            'random_pool_insufficient' => get_string('qissue_poolcounts', 'tool_coursecompliance', (object)[
                'available' => (int)($details['available'] ?? 0),
                'required' => (int)($details['required'] ?? 0),
            ]),
            'no_correct_answer' => self::correct_answer_detail($details),
            'question_not_ready' => !empty($details['status'])
                ? get_string('qissue_statusvalue', 'tool_coursecompliance', (string)$details['status'])
                : '',
            'broken_question',
            'correct_answer_unchecked',
            'random_pool_broken',
            'structure_error' => !empty($details['error']) ? (string)$details['error'] : '',
            default => '',
        };
    }

    /**
     * Link to edit a question in the native question-bank editor.
     *
     * @param \stdClass $issue Detailed issue.
     * @param string $returnurl Return URL.
     * @return \moodle_url|null
     */
    public static function question_edit_url(\stdClass $issue, string $returnurl = ''): ?\moodle_url {
        if (empty($issue->questionid)) {
            return null;
        }
        $params = [
            'id' => (int)$issue->questionid,
            'courseid' => (int)$issue->courseid,
        ];
        if ($returnurl !== '') {
            $params['returnurl'] = $returnurl;
        }
        return new \moodle_url('/question/bank/editquestion/question.php', $params);
    }

    /**
     * Native question-bank list URL for the course.
     *
     * @param int $courseid Course id.
     * @return \moodle_url
     */
    public static function question_bank_url(int $courseid): \moodle_url {
        return \core_question\local\bank\question_bank_helper::get_url_for_qbank_list($courseid);
    }

    /**
     * Native Quiz structure URL.
     *
     * @param \stdClass $issue Detailed issue.
     * @return \moodle_url|null
     */
    public static function quiz_url(\stdClass $issue): ?\moodle_url {
        if (empty($issue->cmid)) {
            return null;
        }
        return new \moodle_url('/mod/quiz/edit.php', ['cmid' => (int)$issue->cmid]);
    }

    /**
     * Key used to correlate aggregate results and detail rows.
     *
     * @param int $scanid Scan id.
     * @param int $courseid Course id.
     * @param string $assessmenttype Assessment type.
     * @param string $issuecode Detailed issue code.
     * @return string
     */
    private static function detail_key(int $scanid, int $courseid, string $assessmenttype, string $issuecode): string {
        return $scanid . '|' . $courseid . '|' . $assessmenttype . '|' . $issuecode;
    }

    /**
     * Format correct-answer validation details.
     *
     * @param array $details Details.
     * @return string
     */
    private static function correct_answer_detail(array $details): string {
        $reason = (string)($details['reason'] ?? '');
        if ($reason === 'empty_correct_response') {
            return get_string('qissue_correctresponseempty', 'tool_coursecompliance');
        }
        if ($reason === 'correct_response_not_full_credit') {
            return get_string('qissue_correctresponsefraction', 'tool_coursecompliance', (float)($details['fraction'] ?? 0));
        }
        return '';
    }
}
