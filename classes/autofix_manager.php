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
 * Safe automatic remediation support.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Builds conservative remediation batches for unambiguous Quiz settings.
 *
 * The fixer deliberately never creates activities or questions and never
 * guesses missing dates, passwords or networks. Every proposed write is
 * previewed and then applied by the normal conflict-aware batch engine.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autofix_manager {
    /** Review period bit used immediately after an attempt. */
    private const REVIEW_IMMEDIATELY = 0x1000;

    /** Review period bit used while a Quiz is still open. */
    private const REVIEW_OPEN = 0x100;

    /** Review period bit used during an attempt. */
    private const REVIEW_DURING = 0x10000;

    /** Review period bit used after a Quiz closes. */
    private const REVIEW_CLOSED = 0x10;

    /** @var string[] Review fields stored directly on the Quiz record. */
    private const REVIEW_FIELDS = [
        'reviewattempt',
        'reviewcorrectness',
        'reviewmaxmarks',
        'reviewmarks',
        'reviewspecificfeedback',
        'reviewgeneralfeedback',
        'reviewrightanswer',
        'reviewoverallfeedback',
    ];

    /**
     * Determine whether a stored violation can be remediated safely.
     *
     * @param \stdClass $result Scan result.
     * @param array $config Profile configuration.
     * @return bool
     */
    public static function is_fixable(\stdClass $result, array $config): bool {
        if ((string)$result->component !== 'quiz' || empty($result->objectid)) {
            return false;
        }

        $rulecode = (string)$result->rulecode;
        if (in_array(
            $rulecode,
            [
                'QUIZ_ATTEMPTS_STANDARD',
                'QUIZ_TIMELIMIT_STANDARD',
                'QUIZ_GRADE_STANDARD',
                'QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN',
                'QUIZ_CORRECTNESS_HIDDEN_WHILE_OPEN',
                'QUIZ_BROWSER_SECURITY_STANDARD',
                'QUIZ_SHUFFLE_ANSWERS_REQUIRED',
            ],
            true
        )) {
            return self::manual_rule_has_target($rulecode, $config);
        }

        return self::blueprint_rule_is_fixable($result, $config);
    }

    /**
     * Create a preview batch containing all safe fixes from a completed scan.
     *
     * @param int $scanid Scan id.
     * @param int $userid User creating the preview.
     * @return int Batch id.
     */
    public function prepare_scan(int $scanid, int $userid): int {
        global $DB;

        $scan = $DB->get_record('tool_coursecompliance_scan', ['id' => $scanid], '*', MUST_EXIST);
        if ($scan->status !== 'complete') {
            throw new \moodle_exception('autofixscanincomplete', 'tool_coursecompliance');
        }
        $profile = $DB->get_record('tool_coursecompliance_profile', ['id' => $scan->profileid], '*', MUST_EXIST);
        $config = profile_config::decode($profile->configjson);
        $results = $DB->get_records('tool_coursecompliance_result', ['scanid' => $scanid], 'courseid, id');
        $fixable = array_filter(
            $results,
            static fn ($result) => self::is_fixable($result, $config)
        );
        if (!$fixable) {
            throw new \moodle_exception('noautofixableissues', 'tool_coursecompliance');
        }

        $quizids = array_values(array_unique(array_map(
            static fn ($result) => (int)$result->objectid,
            $fixable
        )));
        $quizzes = $DB->get_records_list('quiz', 'id', $quizids);
        $moduleid = (int)$DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        [$instancesql, $instanceparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'autofixquiz');
        $cms = $DB->get_records_select(
            'course_modules',
            "module = :moduleid AND instance $instancesql AND deletioninprogress = 0",
            ['moduleid' => $moduleid] + $instanceparams,
            '',
            'id,course,instance,visible,visibleoncoursepage'
        );
        $cmmap = [];
        foreach ($cms as $cm) {
            $cmmap[(int)$cm->course . ':' . (int)$cm->instance] = $cm;
        }

        $changes = [];
        $seen = [];
        foreach ($fixable as $result) {
            $quizid = (int)$result->objectid;
            $courseid = (int)$result->courseid;
            $quiz = $quizzes[$quizid] ?? null;
            $cm = $cmmap[$courseid . ':' . $quizid] ?? null;
            if (!$quiz || !$cm) {
                continue;
            }
            $context = \context_module::instance((int)$cm->id);
            foreach ($this->plan_result($result, $config, $quiz, $cm) as $field => $newvalue) {
                if ($field === 'visibilitystate') {
                    require_capability('moodle/course:activityvisibility', $context);
                } else {
                    require_capability('moodle/course:manageactivities', $context);
                }
                $oldvalue = $this->current_value($quiz, $cm, $field);
                if ((string)$oldvalue === (string)$newvalue) {
                    continue;
                }
                $key = (int)$cm->id . ':' . $field;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = (string)$newvalue;
                $changes[] = (object)[
                    'batchid' => 0,
                    'cmid' => (int)$cm->id,
                    'courseid' => $courseid,
                    'modname' => 'quiz',
                    'instanceid' => $quizid,
                    'fieldname' => $field,
                    'oldvalue' => (string)$oldvalue,
                    'newvalue' => (string)$newvalue,
                    'status' => 'pending',
                    'message' => '',
                    'timecreated' => time(),
                    'timeapplied' => 0,
                ];
            }
        }

        if (!$changes) {
            throw new \moodle_exception('noautofixableissues', 'tool_coursecompliance');
        }

        $transaction = $DB->start_delegated_transaction();
        $batchid = $DB->insert_record('tool_coursecompliance_act_batch', (object)[
            'userid' => $userid,
            'categoryid' => (int)$scan->categoryid,
            'sourcebatchid' => 0,
            'action' => 'autofix_scan',
            'status' => 'preview',
            'filterjson' => json_encode([
                'scanid' => $scanid,
                'profileid' => (int)$scan->profileid,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary' => get_string('batchsummary_autofix', 'tool_coursecompliance', (object)[
                'scanid' => $scanid,
                'changes' => count($changes),
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
     * Build exact desired values for a single result.
     *
     * @param \stdClass $result Scan result.
     * @param array $config Profile config.
     * @param \stdClass $quiz Current Quiz record.
     * @param \stdClass $cm Current course-module record.
     * @return array<string, int|float|string>
     */
    public function plan_result(\stdClass $result, array $config, \stdClass $quiz, \stdClass $cm): array {
        $rulecode = (string)$result->rulecode;
        if ($rulecode === 'QUIZ_ATTEMPTS_STANDARD') {
            return ['attempts' => (int)($config['examattempts'] ?? 0)];
        }
        if ($rulecode === 'QUIZ_TIMELIMIT_STANDARD') {
            return ['timelimit' => (int)($config['examtimelimit'] ?? 0)];
        }
        if ($rulecode === 'QUIZ_GRADE_STANDARD') {
            return ['grade' => (float)($config['examgrade'] ?? 0)];
        }
        if ($rulecode === 'QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN') {
            $mask = self::REVIEW_IMMEDIATELY | self::REVIEW_OPEN;
            return ['reviewrightanswer' => ((int)$quiz->reviewrightanswer & ~$mask)];
        }
        if ($rulecode === 'QUIZ_CORRECTNESS_HIDDEN_WHILE_OPEN') {
            $mask = self::REVIEW_IMMEDIATELY | self::REVIEW_OPEN;
            return ['reviewcorrectness' => ((int)$quiz->reviewcorrectness & ~$mask)];
        }
        if ($rulecode === 'QUIZ_BROWSER_SECURITY_STANDARD') {
            return ['browsersecurity' => (string)($config['expectedbrowsersecurity'] ?? '')];
        }
        if ($rulecode === 'QUIZ_SHUFFLE_ANSWERS_REQUIRED') {
            return ['shuffleanswers' => 1];
        }

        return $this->plan_blueprint_result($result, $config, $quiz, $cm);
    }

    /**
     * Whether a manual-profile rule has an explicit destination value.
     *
     * @param string $rulecode Rule code.
     * @param array $config Profile config.
     * @return bool
     */
    private static function manual_rule_has_target(string $rulecode, array $config): bool {
        return match ($rulecode) {
            'QUIZ_ATTEMPTS_STANDARD' => (int)($config['examattempts'] ?? 0) > 0,
            'QUIZ_TIMELIMIT_STANDARD' => (int)($config['examtimelimit'] ?? 0) > 0,
            'QUIZ_GRADE_STANDARD' => (float)($config['examgrade'] ?? 0) > 0,
            'QUIZ_RIGHT_ANSWER_HIDDEN_WHILE_OPEN' => !empty($config['hideanswerswhileopen']),
            'QUIZ_CORRECTNESS_HIDDEN_WHILE_OPEN' => !empty($config['hidecorrectnesswhileopen']),
            'QUIZ_BROWSER_SECURITY_STANDARD' => trim((string)($config['expectedbrowsersecurity'] ?? '')) !== '',
            'QUIZ_SHUFFLE_ANSWERS_REQUIRED' => !empty($config['requireshuffleanswers']),
            default => false,
        };
    }

    /**
     * Determine whether a Blueprint rule has an unambiguous target value.
     *
     * @param \stdClass $result Scan result.
     * @param array $config Profile config.
     * @return bool
     */
    private static function blueprint_rule_is_fixable(\stdClass $result, array $config): bool {
        $rulecode = (string)$result->rulecode;
        $supported = [
            'BLUEPRINT_ASSESSMENT_GRADE',
            'BLUEPRINT_ASSESSMENT_ATTEMPTS',
            'BLUEPRINT_ASSESSMENT_TIMELIMIT',
            'BLUEPRINT_ASSESSMENT_SHUFFLEANSWERS',
            'BLUEPRINT_ASSESSMENT_BROWSERSECURITY',
            'BLUEPRINT_ASSESSMENT_REVIEW',
            'BLUEPRINT_ASSESSMENT_VISIBILITY',
        ];
        if (!in_array($rulecode, $supported, true)) {
            return false;
        }
        $type = (string)$result->assessmenttype;
        $rules = $config['blueprintrules']['assessments'][$type] ?? [];
        if (!$rules) {
            return false;
        }
        $field = self::blueprint_field($rulecode);
        $sourcekey = $field === 'visible' ? 'visibility_source' : $field . '_source';
        $source = (string)($rules[$sourcekey] ?? 'ignore');
        if (!in_array($source, ['manual', 'template'], true)) {
            return false;
        }

        $idnumber = trim((string)($config[$type . 'idnumber'] ?? $type));
        $template = $config['blueprint']['activities'][$idnumber] ?? null;
        if ($source === 'template' && !is_array($template)) {
            return false;
        }
        $templatesettings = is_array($template) ? ($template['settings'] ?? []) : [];
        if ($field === 'review') {
            if ($source === 'template') {
                foreach (self::REVIEW_FIELDS as $reviewfield) {
                    if (!array_key_exists($reviewfield, $templatesettings)) {
                        return false;
                    }
                }
                return true;
            }
            $manualreview = $rules['review_manual'] ?? null;
            if (!is_array($manualreview)) {
                return false;
            }
            foreach (self::REVIEW_FIELDS as $reviewfield) {
                if (!array_key_exists($reviewfield, $manualreview) || !is_array($manualreview[$reviewfield])) {
                    return false;
                }
            }
            return true;
        }
        if ($field === 'visible') {
            return $source === 'manual' || array_key_exists('visible', $template);
        }

        $expected = $source === 'template'
            ? ($templatesettings[$field] ?? null)
            : ($rules[$field . '_manual'] ?? null);
        if ($expected === null) {
            return false;
        }
        return match ($field) {
            'grade' => (float)$expected > 0,
            'attempts', 'timelimit' => is_numeric($expected) && (int)$expected >= 0,
            'shuffleanswers' => in_array((int)$expected, [0, 1], true),
            'browsersecurity' => is_scalar($expected),
            default => false,
        };
    }

    /**
     * Build desired values for a Blueprint result.
     *
     * @param \stdClass $result Scan result.
     * @param array $config Profile config.
     * @param \stdClass $quiz Current Quiz.
     * @param \stdClass $cm Current course module.
     * @return array<string, int|float|string>
     */
    private function plan_blueprint_result(\stdClass $result, array $config, \stdClass $quiz, \stdClass $cm): array {
        if (!self::blueprint_rule_is_fixable($result, $config)) {
            return [];
        }
        $type = (string)$result->assessmenttype;
        $rules = $config['blueprintrules']['assessments'][$type] ?? [];
        $idnumber = trim((string)($config[$type . 'idnumber'] ?? $type));
        $template = $config['blueprint']['activities'][$idnumber] ?? [];
        $templatesettings = $template['settings'] ?? [];
        $field = self::blueprint_field((string)$result->rulecode);

        if ($field === 'review') {
            $source = (string)($rules['review_source'] ?? 'ignore');
            $values = [];
            foreach (self::REVIEW_FIELDS as $reviewfield) {
                $values[$reviewfield] = $source === 'template'
                    ? (int)($templatesettings[$reviewfield] ?? 0)
                    : self::encode_review_field($rules['review_manual'][$reviewfield] ?? []);
            }
            return $values;
        }
        if ($field === 'visible') {
            $source = (string)($rules['visibility_source'] ?? 'ignore');
            $expected = $source === 'template'
                ? (int)($template['visible'] ?? 1)
                : (int)($rules['visibility_manual'] ?? 1);
            if ($expected) {
                $state = !empty($cm->visibleoncoursepage) ? 'show' : 'stealth';
                return ['visibilitystate' => $state];
            }
            return ['visibilitystate' => 'hide'];
        }

        $source = (string)($rules[$field . '_source'] ?? 'ignore');
        $expected = $source === 'template'
            ? ($templatesettings[$field] ?? null)
            : ($rules[$field . '_manual'] ?? null);
        if ($expected === null) {
            return [];
        }
        return [$field => $expected];
    }

    /**
     * Map a Blueprint rule code to a Quiz field.
     *
     * @param string $rulecode Rule code.
     * @return string
     */
    private static function blueprint_field(string $rulecode): string {
        return match ($rulecode) {
            'BLUEPRINT_ASSESSMENT_GRADE' => 'grade',
            'BLUEPRINT_ASSESSMENT_ATTEMPTS' => 'attempts',
            'BLUEPRINT_ASSESSMENT_TIMELIMIT' => 'timelimit',
            'BLUEPRINT_ASSESSMENT_SHUFFLEANSWERS' => 'shuffleanswers',
            'BLUEPRINT_ASSESSMENT_BROWSERSECURITY' => 'browsersecurity',
            'BLUEPRINT_ASSESSMENT_REVIEW' => 'review',
            'BLUEPRINT_ASSESSMENT_VISIBILITY' => 'visible',
            default => '',
        };
    }

    /**
     * Encode the visual review matrix to Moodle's bit mask.
     *
     * @param array $periods Period flags.
     * @return int
     */
    private static function encode_review_field(array $periods): int {
        $value = 0;
        if (!empty($periods['during'])) {
            $value |= self::REVIEW_DURING;
        }
        if (!empty($periods['immediately'])) {
            $value |= self::REVIEW_IMMEDIATELY;
        }
        if (!empty($periods['open'])) {
            $value |= self::REVIEW_OPEN;
        }
        if (!empty($periods['closed'])) {
            $value |= self::REVIEW_CLOSED;
        }
        return $value;
    }

    /**
     * Read the current value for a proposed change.
     *
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $cm Course module.
     * @param string $field Field name.
     * @return int|float|string
     */
    private function current_value(\stdClass $quiz, \stdClass $cm, string $field): int|float|string {
        if ($field === 'visibilitystate') {
            if (empty($cm->visible)) {
                return 'hide';
            }
            return !empty($cm->visibleoncoursepage) ? 'show' : 'stealth';
        }
        if (!property_exists($quiz, $field)) {
            throw new \coding_exception('Unsupported autofix field: ' . $field);
        }
        return $quiz->{$field};
    }
}
