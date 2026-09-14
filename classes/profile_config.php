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
 * Profile configuration helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Normalises compliance profile configuration.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_config {
    /**
     * Get profile defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'examidnumber' => 'exam',
            'resit1idnumber' => 'resit1',
            'resit2idnumber' => 'resit2',
            'requirecompletion' => true,
            'requireteacher' => true,
            'requireexam' => true,
            'requireresit1' => false,
            'requireresit2' => false,
            'examattempts' => 1,
            'examtimelimit' => 0,
            'examgrade' => 0,
            'requiregradepass' => false,
            'mingradepass' => 0,
            'minquestions' => 1,
            'maxquestions' => 0,
            'hideanswerswhileopen' => true,
            'hidecorrectnesswhileopen' => false,
            'requirepassword' => false,
            'requiresubnet' => false,
            'expectedbrowsersecurity' => '',
            'requireshuffleanswers' => false,
            'warnoverrides' => true,
            'maxoverrideattempts' => 0,
            'maxoverrideextension' => 0,
            'checkquestionintegrity' => true,
            'requirequestionready' => true,
            'requirecorrectanswers' => true,
            'requiremarksmatch' => true,
            'requiresinglebankcategory' => true,
            'blueprintmode' => 'manual',
            'templatecourseid' => 0,
            'blueprintversion' => 0,
            'blueprintcheckcourse' => true,
            'blueprintchecksections' => false,
            'blueprintcheckavailability' => true,
            'blueprintcheckactivitycompletion' => true,
            'blueprintcheckgradebook' => true,
            'blueprintcheckcoursecompletion' => true,
            'blueprint' => [],
            'blueprintrules' => self::blueprint_rule_defaults(),
        ];
    }

    /**
     * Default rule set for the visual Blueprint constructor.
     *
     * @return array<string, mixed>
     */
    public static function blueprint_rule_defaults(): array {
        $assessment = [
            'required' => false,
            'gradepass_source' => 'template',
            'gradepass_manual' => 0,
            'grade_source' => 'ignore',
            'grade_manual' => 0,
            'attempts_source' => 'ignore',
            'attempts_manual' => 1,
            'timelimit_source' => 'ignore',
            'timelimit_manual' => 0,
            'grademethod_source' => 'ignore',
            'grademethod_manual' => 1,
            'shuffleanswers_source' => 'ignore',
            'shuffleanswers_manual' => 0,
            'groupmode_source' => 'template',
            'groupmode_manual' => 0,
            'visibility_source' => 'ignore',
            'visibility_manual' => 1,
            'availability_source' => 'template',
            'availability_manual' => '',
            'completion_source' => 'template',
            'completion_manual' => '',
            'review_source' => 'template',
            'review_manual' => self::review_defaults(),
            'password_source' => 'ignore',
            'password_manual' => 0,
            'subnet_source' => 'ignore',
            'subnet_manual' => 0,
            'browsersecurity_source' => 'ignore',
            'browsersecurity_manual' => '',
            'dates_source' => 'ignore',
            'questions_min' => 0,
            'questions_max' => 0,
            'zero_mark_forbidden' => false,
            'question_integrity' => true,
            'question_ready_only' => true,
            'question_correct_answer' => true,
            'marks_match_grade' => true,
            'single_bank_category' => true,
        ];

        return [
            'enabled' => true,
            'course' => [
                'showactivitydates_source' => 'template',
                'showactivitydates_manual' => 1,
                'maxbytes_source' => 'template',
                'maxbytes_manual' => 0,
                'enablecompletion_source' => 'template',
                'enablecompletion_manual' => 1,
                'groupmode_source' => 'template',
                'groupmode_manual' => 0,
                'groupmodeforce_source' => 'ignore',
                'groupmodeforce_manual' => 0,
            ],
            'content' => [
                'ordinaryquiz_mode' => 'template',
                'ordinaryquiz_value' => 0,
                'page_mode' => 'template',
                'page_value' => 0,
                'pagekeyword' => '',
                'pagekeyword_mode' => 'ignore',
                'pagekeyword_value' => 1,
                'literature_aliases' => "Учебная литература\nЛитература",
                'literature_mode' => 'atleastone',
                'section_source' => 'ignore',
                'file_mode' => 'ignore',
                'file_value' => 0,
                'folder_mode' => 'ignore',
                'folder_value' => 0,
                'url_mode' => 'ignore',
                'url_value' => 0,
                'book_mode' => 'ignore',
                'book_value' => 0,
                'assign_mode' => 'ignore',
                'assign_value' => 0,
                'forum_mode' => 'ignore',
                'forum_value' => 0,
                'uniqueidnumbers' => true,
            ],
            'assessments' => [
                'exam' => array_replace($assessment, ['required' => true]),
                'resit1' => array_replace($assessment, ['required' => true]),
                'resit2' => array_replace($assessment, ['required' => true]),
            ],
            'gradebook' => [
                'formula_source' => 'ignore',
                'formula_idnumber' => '',
                'formula_manual' => '',
                'coursegrademax_source' => 'template',
                'coursegrademax_manual' => 100,
            ],
            'completion' => [
                'source' => 'template',
                'manual' => '',
            ],
        ];
    }

    /**
     * Review-option defaults used by the visual matrix.
     *
     * @return array<string, array<string, int>>
     */
    public static function review_defaults(): array {
        $periods = ['during', 'immediately', 'open', 'closed'];
        $fields = [
            'reviewattempt',
            'reviewcorrectness',
            'reviewmaxmarks',
            'reviewmarks',
            'reviewspecificfeedback',
            'reviewgeneralfeedback',
            'reviewrightanswer',
            'reviewoverallfeedback',
        ];
        $result = [];
        foreach ($fields as $field) {
            foreach ($periods as $period) {
                $result[$field][$period] = 0;
            }
        }
        // These two values are fixed by the standard Moodle Quiz settings form.
        $result['reviewattempt']['during'] = 1;
        $result['reviewoverallfeedback']['during'] = 0;
        return $result;
    }

    /**
     * Decode and merge a stored configuration JSON value.
     *
     * @param string|null $json Stored JSON.
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array {
        $decoded = json_decode($json ?: '{}', true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $defaults = self::defaults();
        $config = array_replace($defaults, $decoded);
        $storedrules = is_array($decoded['blueprintrules'] ?? null) ? $decoded['blueprintrules'] : [];
        $config['blueprintrules'] = array_replace_recursive(self::blueprint_rule_defaults(), $storedrules);
        return $config;
    }

    /**
     * Encode configuration for storage.
     *
     * @param array $config Configuration.
     * @return string
     */
    public static function encode(array $config): string {
        $merged = array_replace(self::defaults(), $config);
        $rules = is_array($config['blueprintrules'] ?? null) ? $config['blueprintrules'] : [];
        $merged['blueprintrules'] = array_replace_recursive(self::blueprint_rule_defaults(), $rules);
        return json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Create a compact default profile configuration for a profile kind.
     *
     * @param string $kind Profile kind: exam or practice.
     * @return array<string, mixed>
     */
    public static function preset(string $kind): array {
        $config = self::defaults();
        if ($kind === 'practice') {
            $config['requireexam'] = false;
            $config['requireresit1'] = false;
            $config['requireresit2'] = false;
            $config['minquestions'] = 0;
            $config['hideanswerswhileopen'] = false;
            $config['warnoverrides'] = false;
            $config['blueprintrules']['assessments']['exam']['required'] = false;
            $config['blueprintrules']['assessments']['resit1']['required'] = false;
            $config['blueprintrules']['assessments']['resit2']['required'] = false;
        }
        return $config;
    }
}
