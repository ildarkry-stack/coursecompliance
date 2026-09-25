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
 * Install schema consistency tests.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_coursecompliance;

/**
 * Check that install.xml keys and indexes reference fields that actually exist.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class schema_definition_test extends \advanced_testcase {
    /**
     * Every key and index field in install.xml must be declared on its table.
     */
    public function test_install_xml_references_existing_fields(): void {
        $path = dirname(__DIR__) . '/db/install.xml';
        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml);

        foreach ($xml->TABLES->TABLE as $table) {
            $tablename = (string)$table['NAME'];
            $fields = [];
            foreach ($table->FIELDS->FIELD as $field) {
                $fields[] = (string)$field['NAME'];
            }

            foreach ($table->KEYS->KEY ?? [] as $key) {
                $this->assert_declared_fields($tablename, $fields, (string)$key['FIELDS'], 'key');
            }
            foreach ($table->INDEXES->INDEX ?? [] as $index) {
                $this->assert_declared_fields($tablename, $fields, (string)$index['FIELDS'], 'index');
            }
        }
    }

    /**
     * Reliability telemetry fields are present on clean installations.
     */
    public function test_scan_table_contains_reliability_metrics(): void {
        $path = dirname(__DIR__) . '/db/install.xml';
        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml);

        $fields = [];
        foreach ($xml->TABLES->TABLE as $table) {
            if ((string)$table['NAME'] !== 'tool_coursecompliance_scan') {
                continue;
            }
            foreach ($table->FIELDS->FIELD as $field) {
                $fields[] = (string)$field['NAME'];
            }
        }

        $required = [
            'durationms', 'dbqueries', 'peakmemory', 'retrycount',
            'configsnapshot', 'confighash', 'finaldurationms', 'finaldbqueries',
        ];
        foreach ($required as $field) {
            $this->assertContains($field, $fields);
        }
    }

    /**
     * Assert that a comma-separated field list only contains declared fields.
     *
     * @param string $tablename Table name.
     * @param array $declaredfields Declared field names.
     * @param string $fieldlist Comma-separated field list.
     * @param string $kind Definition kind for the failure message.
     */
    private function assert_declared_fields(
        string $tablename,
        array $declaredfields,
        string $fieldlist,
        string $kind
    ): void {
        if ($fieldlist === '') {
            return;
        }

        foreach (array_map('trim', explode(',', $fieldlist)) as $field) {
            $this->assertContains(
                $field,
                $declaredfields,
                "The {$kind} on {$tablename} references undeclared field {$field}."
            );
        }
    }
}
