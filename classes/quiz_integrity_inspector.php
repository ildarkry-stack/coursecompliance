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
 *
 * The inspector deliberately uses the Quiz question-bank API instead of
 * reconstructing question references from legacy quiz tables. This keeps the
 * audit compatible with fixed questions, always-latest references and random
 * question sets introduced by the modern question bank.
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
        ];

        try {
            $cm = get_coursemodule_from_instance('quiz', (int)$quiz->id, (int)$quiz->course, false, MUST_EXIST);
            $context = \context_module::instance((int)$cm->id);

            $slots = \mod_quiz\question\bank\qbank_helper::get_question_structure((int)$quiz->id, $context);
            $result['structurequestioncount'] = count($slots);

            $fixedquestionids = [];
            $randomfilters = [];
            foreach ($slots as $slot) {
                $result['slotmarksum'] += (float)($slot->maxmark ?? 0);
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
                        continue;
                    }
                    $candidateids = array_map('intval', array_keys($candidates));
                    $available = count($candidateids);
                    if ($available === 0) {
                        $result['emptyrandompoolcount']++;
                    }
                    if ($available < (int)$randomfilter['slots']) {
                        $result['insufficientrandompoolcount']++;
                    }
                    $questionids = array_merge($questionids, $candidateids);
                }
            }

            $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
            $result['bankquestioncount'] = count($questionids);

            if ($questionids) {
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
                        "SELECT q.id, q.name, q.questiontext, q.qtype, qv.status
                           FROM {question} q
                           JOIN {question_versions} qv ON qv.questionid = q.id
                           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                          WHERE qbe.questioncategoryid $categorysql
                            AND qv.version = (
                                SELECT MAX(qv2.version)
                                  FROM {question_versions} qv2
                                 WHERE qv2.questionbankentryid = qv.questionbankentryid
                            )",
                        $categoryparams
                    );
                    $readyids = [];
                    $contenthashes = [];
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
                        $contenthashes[$hash] = ($contenthashes[$hash] ?? 0) + 1;
                    }
                    $result['categoryquestioncount'] = count($readyids);
                    $usedlookup = array_fill_keys($questionids, true);
                    foreach ($readyids as $readyid) {
                        if (!isset($usedlookup[$readyid])) {
                            $result['unusedbankquestioncount']++;
                        }
                    }
                    foreach ($contenthashes as $count) {
                        if ($count > 1) {
                            $result['duplicatecontentcount'] += $count - 1;
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
                    continue;
                }

                if (property_exists($question, 'status') && (string)$question->status !== 'ready') {
                    $result['draftquestioncount']++;
                }
                if (!($question instanceof \question_automatically_gradable)) {
                    continue;
                }

                try {
                    $correct = $question->get_correct_response();
                    if ($correct === null || $correct === []) {
                        $result['nocorrectanswercount']++;
                        continue;
                    }
                    [$fraction] = $question->grade_response($correct);
                    if ((float)$fraction < 0.999999) {
                        $result['nocorrectanswercount']++;
                    }
                } catch (\Throwable $e) {
                    // Some valid question types need attempt/variant state before a correct response can be graded.
                    // Report that the deep check was incomplete instead of falsely marking the question as broken.
                    $result['correctansweruncheckedcount']++;
                }
            }

            // Ask the Quiz API to load the question structure as Moodle itself would.
            // This catches stale references or malformed question definitions which
            // simple SQL counts cannot reliably detect.
            $quizsettings = \mod_quiz\quiz_settings::create((int)$quiz->id);
            $quizsettings->preload_questions();
            $quizsettings->load_questions();
        } catch (\Throwable $e) {
            $result['structureerror'] = self::clean_error($e->getMessage());
        }

        return $result;
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
