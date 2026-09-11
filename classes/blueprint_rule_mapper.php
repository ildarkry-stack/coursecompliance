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
 * Blueprint rule form mapping helpers.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Maps nested Blueprint rules to and from Moodle form fields.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class blueprint_rule_mapper {
    /**
     * Convert nested rules to form data.
     *
     * @param array $rules Rule configuration.
     * @param int $profileid Profile id.
     * @return \stdClass
     */
    public static function to_form_data(array $rules, int $profileid): \stdClass {
        $data = ['profileid' => $profileid, 'rules_enabled' => !empty($rules['enabled']) ? 1 : 0];
        self::flatten_section($data, 'course', $rules['course'] ?? []);
        self::flatten_section($data, 'content', $rules['content'] ?? []);
        foreach (['exam', 'resit1', 'resit2'] as $type) {
            $assessment = $rules['assessments'][$type] ?? [];
            foreach ($assessment as $key => $value) {
                if ($key === 'review_manual' && is_array($value)) {
                    foreach ($value as $field => $periods) {
                        foreach ($periods as $period => $enabled) {
                            $data['assessment_' . $type . '_review_' . $field . '_' . $period] = (int)$enabled;
                        }
                    }
                    continue;
                }
                $data['assessment_' . $type . '_' . $key] = $value;
            }
        }
        self::flatten_section($data, 'gradebook', $rules['gradebook'] ?? []);
        self::flatten_section($data, 'completion', $rules['completion'] ?? []);
        return (object)$data;
    }

    /**
     * Convert submitted form data to nested rules.
     *
     * @param \stdClass $data Form data.
     * @param array $current Existing rule configuration.
     * @return array<string, mixed>
     */
    public static function from_form_data(\stdClass $data, array $current): array {
        $defaults = profile_config::blueprint_rule_defaults();
        $rules = array_replace_recursive($defaults, $current);
        $rules['enabled'] = !empty($data->rules_enabled);

        foreach (['course', 'content', 'gradebook', 'completion'] as $section) {
            foreach ($rules[$section] as $key => $default) {
                $field = $section . '_' . $key;
                if (!property_exists($data, $field)) {
                    continue;
                }
                $rules[$section][$key] = self::cast_like($data->{$field}, $default);
            }
        }

        foreach (['exam', 'resit1', 'resit2'] as $type) {
            foreach ($rules['assessments'][$type] as $key => $default) {
                if ($key === 'review_manual') {
                    foreach ($default as $reviewfield => $periods) {
                        foreach ($periods as $period => $enabled) {
                            $field = 'assessment_' . $type . '_review_' . $reviewfield . '_' . $period;
                            $rules['assessments'][$type]['review_manual'][$reviewfield][$period] =
                                !empty($data->{$field}) ? 1 : 0;
                        }
                    }
                    continue;
                }
                $field = 'assessment_' . $type . '_' . $key;
                if (!property_exists($data, $field)) {
                    continue;
                }
                $rules['assessments'][$type][$key] = self::cast_like($data->{$field}, $default);
            }
        }
        return $rules;
    }

    /**
     * Flatten a simple rule section.
     *
     * @param array $target Form data collector.
     * @param string $prefix Field prefix.
     * @param array $values Values.
     */
    private static function flatten_section(array &$target, string $prefix, array $values): void {
        foreach ($values as $key => $value) {
            $target[$prefix . '_' . $key] = $value;
        }
    }

    /**
     * Cast a form value to the type used by the default configuration.
     *
     * @param mixed $value Submitted value.
     * @param mixed $default Default value.
     * @return mixed
     */
    private static function cast_like($value, $default) {
        if (is_bool($default)) {
            return !empty($value);
        }
        if (is_int($default)) {
            return (int)$value;
        }
        if (is_float($default)) {
            return (float)$value;
        }
        return (string)$value;
    }
}
