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
 * Question-bank integrity rule registry.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

use tool_coursecompliance\local\rule\callback_rule;
use tool_coursecompliance\local\rule\violation;

/**
 * Isolated registry for Quiz/question-bank integrity checks.
 */
final class quiz_integrity_rule_registry {
    /**
     * Return question-bank integrity rules.
     *
     * @return array<int, \tool_coursecompliance\local\rule\rule>
     */
    public static function rules(): array {
        return [
            new callback_rule(
                'QUIZ_QUESTIONS_PRESENT',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['visualblueprint']) || !empty($ctx['questioncount'])) {
                        return null;
                    }
                    return self::quiz_violation($ctx['quiz'], 'v_quiz_questions', '>= 1', '0');
                }
            ),
            new callback_rule(
                'QUIZ_ZERO_MARK_QUESTIONS',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['visualblueprint']) || empty($ctx['zeromarkcount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_zero_marks',
                        '0',
                        (string)$ctx['zeromarkcount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_SUMGRADES_POSITIVE',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (
                        empty($ctx['visualblueprint'])
                        || empty($ctx['questioncount'])
                        || (float)$ctx['quiz']->sumgrades > 0
                    ) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_sumgrades',
                        '> 0',
                        (string)$ctx['quiz']->sumgrades
                    );
                }
            ),
            new callback_rule(
                'QUIZ_STRUCTURE_LOADABLE',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || trim((string)$ctx['structureerror']) === '') {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_structure_error',
                        get_string('loadable', 'tool_coursecompliance'),
                        (string)$ctx['structureerror']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_RANDOM_POOL_EMPTY',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || empty($ctx['emptyrandompoolcount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_random_pool_empty',
                        '0',
                        (string)$ctx['emptyrandompoolcount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_RANDOM_POOL_INSUFFICIENT',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || empty($ctx['insufficientrandompoolcount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_random_pool_insufficient',
                        '0',
                        (string)$ctx['insufficientrandompoolcount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_BROKEN_QUESTIONS',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || empty($ctx['brokenquestioncount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_broken_questions',
                        '0',
                        (string)$ctx['brokenquestioncount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_QUESTION_CORRECT_ANSWER_REQUIRED',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['requirecorrectanswers']) || empty($ctx['nocorrectanswercount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_no_correct_answer',
                        '0',
                        (string)$ctx['nocorrectanswercount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_CORRECT_ANSWER_CHECK_PARTIAL',
                'quiz',
                'notice',
                static function (array $ctx): ?violation {
                    if (empty($ctx['requirecorrectanswers']) || empty($ctx['correctansweruncheckedcount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_correct_answer_check_partial',
                        get_string('completecheck', 'tool_coursecompliance'),
                        (string)$ctx['correctansweruncheckedcount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_QUESTION_READY_ONLY',
                'quiz',
                'warning',
                static function (array $ctx): ?violation {
                    if (empty($ctx['requirequestionready']) || empty($ctx['draftquestioncount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_draft_questions',
                        '0',
                        (string)$ctx['draftquestioncount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_SLOT_SUMGRADES_CONSISTENT',
                'quiz',
                'critical',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || empty($ctx['questioncount'])) {
                        return null;
                    }
                    $slotmarksum = (float)$ctx['slotmarksum'];
                    $sumgrades = (float)$ctx['quiz']->sumgrades;
                    if (abs($slotmarksum - $sumgrades) < 0.00001) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_slot_sumgrades_mismatch',
                        (string)$sumgrades,
                        (string)$slotmarksum
                    );
                }
            ),
            new callback_rule(
                'QUIZ_MARK_DISTRIBUTION_MATCH',
                'quiz',
                'warning',
                static function (array $ctx): ?violation {
                    if (empty($ctx['requiremarksmatch']) || empty($ctx['questioncount'])) {
                        return null;
                    }
                    $slotmarksum = (float)$ctx['slotmarksum'];
                    $quizgrade = (float)$ctx['quiz']->grade;
                    if ($slotmarksum <= 0 || abs($slotmarksum - $quizgrade) < 0.00001) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_mark_distribution',
                        (string)$quizgrade,
                        (string)$slotmarksum
                    );
                }
            ),
            new callback_rule(
                'QUIZ_SINGLE_BANK_CATEGORY',
                'quiz',
                'warning',
                static function (array $ctx): ?violation {
                    if (empty($ctx['requiresinglebankcategory']) || (int)$ctx['bankcategorycount'] <= 1) {
                        return null;
                    }
                    $categories = is_array($ctx['bankcategories'] ?? null) ? $ctx['bankcategories'] : [];
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_multiple_bank_categories',
                        '1',
                        implode(', ', array_slice($categories, 0, 8))
                    );
                }
            ),
            new callback_rule(
                'QUIZ_BANK_DUPLICATE_CONTENT',
                'quiz',
                'warning',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || empty($ctx['duplicatecontentcount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_bank_duplicate_content',
                        '0',
                        (string)$ctx['duplicatecontentcount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_BANK_UNUSED_QUESTIONS',
                'quiz',
                'notice',
                static function (array $ctx): ?violation {
                    if (empty($ctx['checkquestionintegrity']) || empty($ctx['unusedbankquestioncount'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_bank_unused_questions',
                        '0',
                        (string)$ctx['unusedbankquestioncount']
                    );
                }
            ),
            new callback_rule(
                'QUIZ_INTEGRITY_PARTIAL',
                'quiz',
                'notice',
                static function (array $ctx): ?violation {
                    if (empty($ctx['deepchecktruncated'])) {
                        return null;
                    }
                    return self::quiz_violation(
                        $ctx['quiz'],
                        'v_quiz_integrity_partial',
                        get_string('completecheck', 'tool_coursecompliance'),
                        get_string('partialcheck', 'tool_coursecompliance')
                    );
                }
            ),
        ];
    }

    /**
     * Build a violation linked to a Quiz.
     *
     * @param \stdClass $quiz Quiz record.
     * @param string $stringkey Language string key.
     * @param string $expected Expected value.
     * @param string $actual Actual value.
     * @return violation
     */
    private static function quiz_violation(
        \stdClass $quiz,
        string $stringkey,
        string $expected,
        string $actual
    ): violation {
        return new violation(
            get_string($stringkey, 'tool_coursecompliance'),
            $expected,
            $actual,
            (int)$quiz->id,
            (string)$quiz->name
        );
    }
}
