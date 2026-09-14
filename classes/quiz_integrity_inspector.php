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
 * Quiz and question-bank integrity inspection.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Performs read-only checks that mirror how Moodle resolves Quiz questions.
 */
final class quiz_integrity_inspector {
    /** Maximum number of question definitions loaded for deep validation in one Quiz. */
    private const MAX_DEEP_QUESTIONS = 500;

    /**
     * Inspect one Quiz and its question-bank references.
     *
     * @param \stdClass $quiz Quiz record. Must contain id and course.
     * @return array<string, mixed>
     */
    public static function inspect(\stdClass $quiz): array {
        global $DB;

        $result = [
            'structureerror' => '',
            'structurequestioncount' => 0,
            'slotmarksum' => 0.0,
            'bankquestioncount' => 0,
            'bankcategorycount' => 0,
            'bankcategories' => [],
            'categoryquestioncount' => 0,
            'unusedbankquestioncount' => 0,
            'duplicatecontentcount' => 0,
            'draftquestioncount' => 0,
            'brokenquestioncount' => 0,
            'nocorrectanswercount' => 0,
            'correctansweruncheckedcount' => 0,
            'randompoolcount' => 0,
            'emptyrandompoolcount' => 0,
            'insufficientrandompoolcount' => 0,
            'deepchecktruncated' => false,
            'issues' => [],
        ];

        try {
            $cm = get_coursemodule_from_instance('quiz', (int)$quiz->id, (int)$quiz->course, false, MUST_EXIST);
            $context = \context_module::instance((int)$cm->id);

            $slots = \mod_quiz\question\bank\qbank_helper::get_question_structure((int)$quiz->id, $context);
            $result['structurequestioncount'] = count($slots);

            $fixedquestionids = [];
            $randomfilters = [];
            $zeromarkslots = [];
            foreach ($slots as $slot) {
                $result['slotmarksum'] += (float)($slot->maxmark ?? 0);
                if ((float)($slot->maxmark ?? 0) <= 0) {
                    $zeromarkslots[] = [
                        'slotnumber' => (int)($slot->slot ?? 0),
                        'questionid' => (int)($slot->questionid ?? 0),
                    ];
                }
                if (!empty($slot->filtercondition) && is_array($slot->filtercondition)) {
                    $filter = $slot->filtercondition['filter'] ?? null;
                    if (is_array($filter) && $filter) {
                        $signature = sha1(json_encode($filter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        if (!isset($randomfilters[$signature])) {
                            $randomfilters[$signature] = [
                                'filter' => $filter,
                                'slots' => 0,
                            ];
                        }
                        $randomfilters[$signature]['slots']++;
                        continue;
                    }
                }
                if (!empty($slot->questionid)) {
                    $fixedquestionids[] = (int)$slot->questionid;
                }
            }

            $questionids = $fixedquestionids;
            if ($randomfilters) {
                $loader = new \core_question\local\bank\random_question_loader(new \qubaid_list([]));
                foreach ($randomfilters as $randomfilter) {
                    $result['randompoolcount']++;
                    try {
                        $candidates = $loader->get_filtered_questions($randomfilter['filter']);
                    } catch (\Throwable $e) {
                        $result['brokenquestioncount']++;
                        $result['issues'][] = self::issue(
                            'random_pool_broken',
                            'critical',
                            null,
                            0,
                            ['error' => self::clean_error($e->getMessage())]
                        );
                        continue;
                    }
                    $candidateids = array_map('intval', array_keys($candidates));
                    $available = count($candidateids);
                    $required = (int)$randomfilter['slots'];
                    if ($available === 0) {
                        $result['emptyrandompoolcount']++;
                        $result['issues'][] = self::issue(
                            'random_pool_empty',
                            'critical',
                            null,
                            0,
                            ['available' => $available, 'required' => $required]
                        );
                    }
                    if ($available < $required) {
                        $result['insufficientrandompoolcount']++;
                        $result['issues'][] = self::issue(
                            'random_pool_insufficient',
                            'critical',
                            null,
                            0,
                            ['available' => $available, 'required' => $required]
                        );
                    }
                    $questionids = array_merge($questionids, $candidateids);
                }
            }

            $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
            $result['bankquestioncount'] = count($questionids);
            $metadata = [];

            if ($questionids) {
                [$metainsql, $metaparams] = $DB->get_in_or_equal(
                    $questionids,
                    SQL_PARAMS_NAMED,
                    'integritymeta'
                );
                $metadata = $DB->get_records_sql(
                    "SELECT q.id, q.name, q.qtype, qv.status, qbe.id AS qbankentryid,
                            qc.id AS categoryid, qc.name AS categoryname
                       FROM {question} q
                       JOIN {question_versions} qv ON qv.questionid = q.id
                       JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                       JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      WHERE q.id $metainsql",
                    $metaparams
                );

                foreach ($zeromarkslots as $slotinfo) {
                    $questionid = (int)$slotinfo['questionid'];
                    $result['issues'][] = self::issue(
                        'zero_mark',
                        'critical',
                        $metadata[$questionid] ?? null,
                        (int)$slotinfo['slotnumber']
                    );
                }

                [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'integrityq');
                $categoryrecords = $DB->get_records_sql(
                    "SELECT DISTINCT qc.id, qc.name
                       FROM {question} q
                       JOIN {question_versions} qv ON qv.questionid = q.id
                       JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                       JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      WHERE q.id $insql
                   ORDER BY qc.name, qc.id",
                    $params
                );
                $result['bankcategories'] = array_values(array_map(
                    static fn ($category): string => (string)$category->name,
                    $categoryrecords
                ));
                $result['bankcategorycount'] = count($categoryrecords);

                $categoryids = array_values(array_map(
                    static fn ($category): int => (int)$category->id,
                    $categoryrecords
                ));
                if ($categoryids) {
                    [$categorysql, $categoryparams] = $DB->get_in_or_equal(
                        $categoryids,
                        SQL_PARAMS_NAMED,
                        'integritycat'
                    );
                    $bankquestions = $DB->get_records_sql(
                        "SELECT q.id, q.name, q.questiontext, q.qtype, qv.status,
                                qbe.id AS qbankentryid, qc.id AS categoryid, qc.name AS categoryname
                           FROM {question} q
                           JOIN {question_versions} qv ON qv.questionid = q.id
                           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                           JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                          WHERE qbe.questioncategoryid $categorysql
                            AND qv.version = (
                                SELECT MAX(qv2.version)
                                  FROM {question_versions} qv2
                                 WHERE qv2.questionbankentryid = qv.questionbankentryid
                            )",
                        $categoryparams
                    );
                    $readyids = [];
                    $contentgroups = [];
                    foreach ($bankquestions as $bankquestion) {
                        if ((string)$bankquestion->status !== 'ready') {
                            continue;
                        }
                        $readyids[] = (int)$bankquestion->id;
                        $normalizedtext = trim(strip_tags((string)$bankquestion->questiontext));
                        $hash = sha1(
                            (string)$bankquestion->qtype . '|' .
                            trim((string)$bankquestion->name) . '|' .
                            preg_replace('/\s+/u', ' ', $normalizedtext)
                        );
                        $contentgroups[$hash][] = $bankquestion;
                    }
                    $result['categoryquestioncount'] = count($readyids);
                    $usedlookup = array_fill_keys($questionids, true);
                    foreach ($readyids as $readyid) {
                        if (!isset($usedlookup[$readyid])) {
                            $result['unusedbankquestioncount']++;
                            $result['issues'][] = self::issue(
                                'unused_question',
                                'notice',
                                $bankquestions[$readyid] ?? null
                            );
                        }
                    }
                    foreach ($contentgroups as $members) {
                        if (count($members) <= 1) {
                            continue;
                        }
                        $original = reset($members);
                        foreach (array_slice($members, 1) as $duplicate) {
                            $result['duplicatecontentcount']++;
                            $result['issues'][] = self::issue(
                                'duplicate_content',
                                'warning',
                                $duplicate,
                                0,
                                ['duplicateof' => (int)$original->id]
                            );
                        }
                    }
                }
            }

            $deepids = $questionids;
            if (count($deepids) > self::MAX_DEEP_QUESTIONS) {
                $deepids = array_slice($deepids, 0, self::MAX_DEEP_QUESTIONS);
                $result['deepchecktruncated'] = true;
            }

            foreach ($deepids as $questionid) {
                try {
                    $question = \question_bank::load_question($questionid);
                } catch (\Throwable $e) {
                    $result['brokenquestioncount']++;
                    $result['issues'][] = self::issue(
                        'broken_question',
                        'critical',
                        $metadata[$questionid] ?? null,
                        0,
                        ['error' => self::clean_error($e->getMessage())]
                    );
                    continue;
                }

                $questionstatus = (string)($metadata[$questionid]->status ?? ($question->status ?? ''));
                if ($questionstatus !== '' && $questionstatus !== 'ready') {
                    $result['draftquestioncount']++;
                    $result['issues'][] = self::issue(
                        'question_not_ready',
                        'warning',
                        $metadata[$questionid] ?? null,
                        0,
                        ['status' => $questionstatus]
                    );
                }
                if (!($question instanceof \question_automatically_gradable)) {
                    continue;
                }

                try {
                    $correct = $question->get_correct_response();
                    if ($correct === null || $correct === []) {
                        $result['nocorrectanswercount']++;
                        $result['issues'][] = self::issue(
                            'no_correct_answer',
                            'critical',
                            $metadata[$questionid] ?? null,
                            0,
                            ['reason' => 'empty_correct_response']
                        );
                        continue;
                    }
                    [$fraction] = $question->grade_response($correct);
                    if ((float)$fraction < 0.999999) {
                        $result['nocorrectanswercount']++;
                        $result['issues'][] = self::issue(
                            'no_correct_answer',
                            'critical',
                            $metadata[$questionid] ?? null,
                            0,
                            [
                                'reason' => 'correct_response_not_full_credit',
                                'fraction' => (float)$fraction,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Some valid question types need attempt/variant state before a correct response can be graded.
                    // Report that the deep check was incomplete instead of falsely marking the question as broken.
                    $result['correctansweruncheckedcount']++;
                    $result['issues'][] = self::issue(
                        'correct_answer_unchecked',
                        'notice',
                        $metadata[$questionid] ?? null,
                        0,
                        ['error' => self::clean_error($e->getMessage())]
                    );
                }
            }

            // Ask the Quiz API to load the question structure as Moodle itself would.
            $quizsettings = \mod_quiz\quiz_settings::create((int)$quiz->id);
            $quizsettings->preload_questions();
            $quizsettings->load_questions();
        } catch (\Throwable $e) {
            $result['structureerror'] = self::clean_error($e->getMessage());
            $result['issues'][] = self::issue(
                'structure_error',
                'critical',
                null,
                0,
                ['error' => $result['structureerror']]
            );
        }

        return $result;
    }

    /**
     * Build a normalized detailed question-bank issue.
     *
     * @param string $issuecode Stable issue code.
     * @param string $severity Severity.
     * @param \stdClass|null $metadata Question metadata when available.
     * @param int $slotnumber Quiz slot number.
     * @param array<string, mixed> $details Additional structured details.
     * @return array<string, mixed>
     */
    private static function issue(
        string $issuecode,
        string $severity,
        ?\stdClass $metadata,
        int $slotnumber = 0,
        array $details = []
    ): array {
        return [
            'issuecode' => $issuecode,
            'severity' => $severity,
            'questionid' => $metadata ? (int)$metadata->id : 0,
            'qbankentryid' => $metadata ? (int)($metadata->qbankentryid ?? 0) : 0,
            'categoryid' => $metadata ? (int)($metadata->categoryid ?? 0) : 0,
            'slotnumber' => $slotnumber,
            'qtype' => $metadata ? (string)($metadata->qtype ?? '') : '',
            'questionstatus' => $metadata ? (string)($metadata->status ?? '') : '',
            'questionname' => $metadata ? (string)($metadata->name ?? '') : '',
            'categoryname' => $metadata ? (string)($metadata->categoryname ?? '') : '',
            'details' => $details,
        ];
    }

    /**
     * Keep technical errors readable and safe for reports.
     *
     * @param string $message Raw exception message.
     * @return string
     */
    private static function clean_error(string $message): string {
        $message = trim(strip_tags($message));
        if ($message === '') {
            return get_string('unknownerror');
        }
        return \core_text::substr($message, 0, 500);
    }
}
