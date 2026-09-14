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
 * Helpers for portable availability rules in Blueprint profiles.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Converts Moodle availability JSON to portable Blueprint references and back.
 *
 * Course-module and grade-item database IDs are course-specific. Blueprint rules
 * therefore store exam/resit dependencies by idnumber and translate them only
 * while the native Moodle availability editor is open.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability_blueprint {
    /**
     * Build ID maps for a template course.
     *
     * @param int $courseid Course id.
     * @param string[] $targetidnumbers Allowed assessment idnumbers.
     * @return array<string, mixed>
     */
    public static function build_maps(int $courseid, array $targetidnumbers): array {
        global $DB;

        $targetidnumbers = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => trim($value),
            $targetidnumbers
        ))));

        $maps = [
            'cmtoidnumber' => [],
            'idnumbertocm' => [],
            'gradeidtoidnumber' => [],
            'idnumbertogradeid' => [],
            'groupidtolabel' => [],
            'labeltogroupid' => [],
            'groupingidtolabel' => [],
            'labeltogroupingid' => [],
            'targetlabels' => [],
        ];
        if (!$targetidnumbers) {
            return $maps;
        }

        [$insql, $params] = $DB->get_in_or_equal($targetidnumbers, SQL_PARAMS_NAMED, 'ccavailid');
        $params['courseid'] = $courseid;
        $sql = "SELECT cm.id, cm.idnumber, cm.instance, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                       AND cm.idnumber $insql";
        $records = $DB->get_records_sql($sql, $params);
        $instances = [];
        foreach ($records as $record) {
            $idnumber = trim((string)$record->idnumber);
            if ($idnumber === '') {
                continue;
            }
            $maps['cmtoidnumber'][(int)$record->id] = $idnumber;
            $maps['idnumbertocm'][$idnumber] = (int)$record->id;
            $instances[(string)$record->modname][(int)$record->instance] = $idnumber;
        }

        if ($records) {
            $modinfo = get_fast_modinfo($courseid);
            foreach ($maps['idnumbertocm'] as $idnumber => $cmid) {
                if (isset($modinfo->cms[$cmid])) {
                    $maps['targetlabels'][$idnumber] = $idnumber . ' — ' . $modinfo->cms[$cmid]->name;
                } else {
                    $maps['targetlabels'][$idnumber] = $idnumber;
                }
            }
        }

        $gradeitems = $DB->get_records('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemnumber' => 0,
        ]);
        foreach ($gradeitems as $item) {
            $modname = (string)$item->itemmodule;
            $instanceid = (int)$item->iteminstance;
            if (!isset($instances[$modname][$instanceid])) {
                continue;
            }
            $idnumber = $instances[$modname][$instanceid];
            $maps['gradeidtoidnumber'][(int)$item->id] = $idnumber;
            $maps['idnumbertogradeid'][$idnumber] = (int)$item->id;
        }

        foreach ($DB->get_records('groups', ['courseid' => $courseid]) as $group) {
            $label = trim((string)$group->idnumber);
            if ($label === '') {
                $label = trim((string)$group->name);
            }
            if ($label === '') {
                continue;
            }
            $maps['groupidtolabel'][(int)$group->id] = $label;
            $maps['labeltogroupid'][$label] = (int)$group->id;
        }
        foreach ($DB->get_records('groupings', ['courseid' => $courseid]) as $grouping) {
            $label = trim((string)$grouping->idnumber);
            if ($label === '') {
                $label = trim((string)$grouping->name);
            }
            if ($label === '') {
                continue;
            }
            $maps['groupingidtolabel'][(int)$grouping->id] = $label;
            $maps['labeltogroupingid'][$label] = (int)$grouping->id;
        }

        return $maps;
    }

    /**
     * Convert raw Moodle availability JSON to portable Blueprint JSON.
     *
     * @param string $json Raw availability JSON.
     * @param array $maps ID maps from build_maps().
     * @return string Portable JSON.
     */
    public static function normalise_json(string $json, array $maps): string {
        $json = trim($json);
        if ($json === '') {
            return '';
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        if (isset($decoded['c']) && is_array($decoded['c']) && !$decoded['c']) {
            return '';
        }

        self::walk_normalise($decoded, $maps);
        self::recursive_ksort($decoded);
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Convert portable Blueprint JSON to template-course Moodle JSON.
     *
     * @param string $json Portable JSON.
     * @param array $maps ID maps from build_maps().
     * @return string Raw Moodle availability JSON.
     */
    public static function denormalise_json(string $json, array $maps): string {
        $json = trim($json);
        if ($json === '') {
            return '';
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }

        self::walk_denormalise($decoded, $maps);
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Check whether a denormalised Moodle availability tree has valid references.
     *
     * Empty restrictions are valid. Completion, grade, group and grouping conditions
     * must resolve to a positive database id before the rule can be applied safely.
     *
     * @param string $json Raw Moodle availability JSON.
     * @return bool True when all referenced ids are resolved.
     */
    public static function is_resolved_json(string $json): bool {
        $json = trim($json);
        if ($json === '') {
            return true;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return false;
        }
        return self::node_is_resolved($decoded);
    }

    /**
     * Validate references in one availability tree node.
     *
     * @param array $node Availability node.
     * @return bool True when the node and its children are resolved.
     */
    private static function node_is_resolved(array $node): bool {
        $type = (string)($node['type'] ?? '');
        if ($type === 'completion' && (int)($node['cm'] ?? 0) <= 0) {
            return false;
        }
        if (in_array($type, ['grade', 'group', 'grouping'], true) && (int)($node['id'] ?? 0) <= 0) {
            return false;
        }
        foreach ($node as $child) {
            if (is_array($child) && !self::node_is_resolved($child)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Normalise one availability tree node recursively.
     *
     * @param array $value Node.
     * @param array $maps ID maps.
     */
    private static function walk_normalise(array &$value, array $maps): void {
        $type = (string)($value['type'] ?? '');
        if ($type === 'completion' && isset($value['cm'])) {
            $value['cmidnumber'] = $maps['cmtoidnumber'][(int)$value['cm']] ?? 'UNRESOLVED';
            unset($value['cm']);
        } else if ($type === 'grade' && isset($value['id'])) {
            $value['gradeidnumber'] = $maps['gradeidtoidnumber'][(int)$value['id']] ?? 'UNRESOLVED';
            unset($value['id']);
        } else if ($type === 'group' && isset($value['id'])) {
            $value['group'] = $maps['groupidtolabel'][(int)$value['id']] ?? 'UNRESOLVED';
            unset($value['id']);
        } else if ($type === 'grouping' && isset($value['id'])) {
            $value['grouping'] = $maps['groupingidtolabel'][(int)$value['id']] ?? 'UNRESOLVED';
            unset($value['id']);
        }

        foreach ($value as &$child) {
            if (is_array($child)) {
                self::walk_normalise($child, $maps);
            }
        }
        unset($child);
    }

    /**
     * Denormalise one availability tree node recursively.
     *
     * @param array $value Node.
     * @param array $maps ID maps.
     */
    private static function walk_denormalise(array &$value, array $maps): void {
        $type = (string)($value['type'] ?? '');
        if ($type === 'completion' && isset($value['cmidnumber'])) {
            $value['cm'] = (int)($maps['idnumbertocm'][(string)$value['cmidnumber']] ?? 0);
            unset($value['cmidnumber']);
        } else if ($type === 'grade' && isset($value['gradeidnumber'])) {
            $value['id'] = (int)($maps['idnumbertogradeid'][(string)$value['gradeidnumber']] ?? 0);
            unset($value['gradeidnumber']);
        } else if ($type === 'group' && isset($value['group'])) {
            $value['id'] = (int)($maps['labeltogroupid'][(string)$value['group']] ?? 0);
            unset($value['group']);
        } else if ($type === 'grouping' && isset($value['grouping'])) {
            $value['id'] = (int)($maps['labeltogroupingid'][(string)$value['grouping']] ?? 0);
            unset($value['grouping']);
        }

        foreach ($value as &$child) {
            if (is_array($child)) {
                self::walk_denormalise($child, $maps);
            }
        }
        unset($child);
    }

    /**
     * Recursively sort associative arrays while retaining list ordering.
     *
     * @param array $value Value to sort.
     */
    private static function recursive_ksort(array &$value): void {
        foreach ($value as &$child) {
            if (is_array($child)) {
                self::recursive_ksort($child);
            }
        }
        unset($child);
        if (!array_is_list($value)) {
            ksort($value);
        }
    }
}
