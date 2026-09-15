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

    /** @var array<int, \stdClass[]> Preloaded answer rows keyed by question id. */
    private static array $answercache = [];

    /** @var array<int, \stdClass> Preloaded multiple-choice options keyed by question id. */
    private static array $multichoicecache = [];

    /** @var array<int, \stdClass[]> Preloaded matching rows keyed by question id. */
    private static array $matchcache = [];

    /**
     * Reset per-process preload caches before a new scan worker starts.
     */
    public static function reset_preload_cache(): void {
        self::$answercache = [];
        self::$multichoicecache = [];
        self::$matchcache = [];
    }

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
            self::preload_validation_data($deepids);

            foreach ($deepids as $questionid) {
                $questionstatus = (string)($metadata[$questionid]->status ?? '');
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

                $qtype = (string)($metadata[$questionid]->qtype ?? '');
                if (in_array($qtype, ['description', 'essay'], true)) {
                    continue;
                }
                if (!in_array($qtype, ['multichoice', 'match', 'shortanswer', 'numerical', 'truefalse'], true)) {
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
                    if (!($question instanceof \question_automatically_gradable)) {
                        continue;
                    }
                }

                $answercheck = self::validate_correct_answer($questionid, $qtype);
                if ($answercheck['status'] === 'invalid') {
                    $result['nocorrectanswercount']++;
                    $result['issues'][] = self::issue(
                        'no_correct_answer',
                        'critical',
                        $metadata[$questionid] ?? null,
                        0,
                        $answercheck['details']
                    );
                } else if ($answercheck['status'] === 'unchecked') {
                    $result['correctansweruncheckedcount']++;
                    $result['issues'][] = self::issue(
                        'correct_answer_unchecked',
                        'notice',
                        $metadata[$questionid] ?? null,
                        0,
                        $answercheck['details']
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
     * Preload answer data used by deep validation in a constant number of queries.
     *
     * @param int[] $questionids Question ids.
     */
    private static function preload_validation_data(array $questionids): void {
        global $DB;

        $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
        if (!$questionids) {
            return;
        }
        $missing = array_values(array_filter(
            $questionids,
            static fn (int $questionid): bool => !array_key_exists($questionid, self::$answercache)
        ));
        if (!$missing) {
            return;
        }
        foreach ($missing as $questionid) {
            self::$answercache[$questionid] = [];
            self::$matchcache[$questionid] = [];
        }

        [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'preloadanswer');
        $answers = $DB->get_records_select('question_answers', "question $insql", $params, 'id ASC');
        foreach ($answers as $answer) {
            self::$answercache[(int)$answer->question][] = $answer;
        }

        [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'preloadmulti');
        $options = $DB->get_records_select(
            'qtype_multichoice_options',
            "questionid $insql",
            $params,
            '',
            'id, questionid, single'
        );
        foreach ($options as $option) {
            self::$multichoicecache[(int)$option->questionid] = $option;
        }

        [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'preloadmatch');
        $subquestions = $DB->get_records_select(
            'qtype_match_subquestions',
            "questionid $insql",
            $params,
            'id ASC',
            'id, questionid, questiontext, answertext'
        );
        foreach ($subquestions as $subquestion) {
            self::$matchcache[(int)$subquestion->questionid][] = $subquestion;
        }
    }

    /**
     * Validate that a core automatically-graded question has a defensible full-credit answer.
     *
     * This deliberately avoids calling get_correct_response()/grade_response() on a bare
     * question object. Several valid question types, notably multichoice and matching with
     * shuffled state, need attempt state before those methods can be graded reliably.
     *
     * @param int $questionid Question id.
     * @param string $qtype Question type.
     * @return array
     */
    private static function validate_correct_answer(int $questionid, string $qtype): array {
        return match ($qtype) {
            'multichoice' => self::validate_multichoice_answer($questionid),
            'match' => self::validate_match_answer($questionid),
            'shortanswer',
            'numerical',
            'truefalse' => self::validate_full_credit_answer_record($questionid, $qtype),
            default => [
                'status' => 'unchecked',
                'details' => [
                    'reason' => 'unsupported_qtype',
                    'qtype' => $qtype,
                ],
            ],
        };
    }

    /**
     * Validate a standard Moodle multiple-choice question from its persisted answer fractions.
     *
     * @param int $questionid Question id.
     * @return array
     */
    private static function validate_multichoice_answer(int $questionid): array {
        $answers = self::$answercache[$questionid] ?? [];
        $answers = array_values(array_filter(
            $answers,
            static fn (\stdClass $answer): bool => trim((string)$answer->answer) !== ''
        ));
        if (count($answers) < 2) {
            return [
                'status' => 'invalid',
                'details' => [
                    'reason' => 'multichoice_too_few_answers',
                    'answers' => count($answers),
                ],
            ];
        }

        $options = self::$multichoicecache[$questionid] ?? null;
        if (!$options) {
            return [
                'status' => 'invalid',
                'details' => ['reason' => 'multichoice_options_missing'],
            ];
        }

        $positivefractions = [];
        $fullcreditcount = 0;
        foreach ($answers as $answer) {
            $fraction = (float)$answer->fraction;
            if ($fraction > 0) {
                $positivefractions[] = $fraction;
            }
            if ($fraction >= 0.999999) {
                $fullcreditcount++;
            }
        }

        if (!empty($options->single)) {
            if ($fullcreditcount !== 1) {
                return [
                    'status' => 'invalid',
                    'details' => [
                        'reason' => 'multichoice_single_full_credit',
                        'fullcreditanswers' => $fullcreditcount,
                    ],
                ];
            }
            return ['status' => 'valid', 'details' => []];
        }

        $positivesum = array_sum($positivefractions);
        if (!$positivefractions || abs($positivesum - 1.0) > 0.000001) {
            return [
                'status' => 'invalid',
                'details' => [
                    'reason' => 'multichoice_multi_fraction_total',
                    'positivefractiontotal' => $positivesum,
                ],
            ];
        }

        return ['status' => 'valid', 'details' => []];
    }

    /**
     * Validate Moodle matching questions without requiring a question-attempt state.
     *
     * Matching questions store the answer key as complete question/answer pairs. Extra
     * answer-only distractors are valid and are not treated as errors.
     *
     * @param int $questionid Question id.
     * @return array
     */
    private static function validate_match_answer(int $questionid): array {
        $subquestions = self::$matchcache[$questionid] ?? [];
        $completepairs = 0;
        foreach ($subquestions as $subquestion) {
            if (
                trim((string)$subquestion->questiontext) !== ''
                && trim((string)$subquestion->answertext) !== ''
            ) {
                $completepairs++;
            }
        }

        if ($completepairs < 2) {
            return [
                'status' => 'invalid',
                'details' => [
                    'reason' => 'match_not_enough_complete_pairs',
                    'completepairs' => $completepairs,
                ],
            ];
        }

        return ['status' => 'valid', 'details' => []];
    }

    /**
     * Validate question types whose persisted answer table directly identifies full credit.
     *
     * @param int $questionid Question id.
     * @param string $qtype Question type.
     * @return array
     */
    private static function validate_full_credit_answer_record(int $questionid, string $qtype): array {
        $answers = self::$answercache[$questionid] ?? [];
        foreach ($answers as $answer) {
            if (trim((string)$answer->answer) === '') {
                continue;
            }
            if ((float)$answer->fraction >= 0.999999) {
                return ['status' => 'valid', 'details' => []];
            }
        }

        return [
            'status' => 'invalid',
            'details' => [
                'reason' => 'no_full_credit_answer_record',
                'qtype' => $qtype,
                'answercount' => count($answers),
            ],
        ];
    }

    /**
     * Build a normalized detailed question-bank issue.
     *
     * @param string $issuecode Stable issue code.
     * @param string $severity Severity.
     * @param \stdClass|null $metadata Question metadata when available.
     * @param int $slotnumber Quiz slot number.
     * @param array $details Additional structured details.
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
