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
 * Upgrade steps for tool_coursecompliance.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade tool_coursecompliance.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_tool_coursecompliance_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090801) {
        $table = new xmldb_table('tool_cc_act_batch');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'preview');
        $table->add_field('filterjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('summary', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('totalchanges', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('applied', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('skipped', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('failed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('created_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('tool_cc_act_change');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('modname', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fieldname', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('oldvalue', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('newvalue', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeapplied', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('batch_fk', XMLDB_KEY_FOREIGN, ['batchid'], 'tool_cc_act_batch', ['id']);
        $table->add_index('batch_cmid_idx', XMLDB_INDEX_NOTUNIQUE, ['batchid', 'cmid']);
        $table->add_index('batch_status_idx', XMLDB_INDEX_NOTUNIQUE, ['batchid', 'status']);
        $table->add_index('course_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090801, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026090802) {
        $table = new xmldb_table('tool_cc_act_batch');
        $field = new xmldb_field('sourcebatchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'categoryid');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090802, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026090803) {
        $table = new xmldb_table('tool_cc_session_issue');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assessmenttype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('modname', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('issuecode', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('batch_fk', XMLDB_KEY_FOREIGN, ['batchid'], 'tool_cc_act_batch', ['id']);
        $table->add_index('batch_issue_idx', XMLDB_INDEX_NOTUNIQUE, ['batchid', 'issuecode']);
        $table->add_index('course_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        if (!(int)get_config('tool_coursecompliance', 'sessiondefaultcategory')) {
            $legacycategory = (int)get_config('local_examdates', 'default_category');
            if ($legacycategory > 0) {
                set_config('sessiondefaultcategory', $legacycategory, 'tool_coursecompliance');
            }
        }

        upgrade_plugin_savepoint(true, 2026090803, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026090804) {
        $table = new xmldb_table('tool_cc_scan');
        $field = new xmldb_field('ignored', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'notices');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('tool_cc_result');
        $field = new xmldb_field('assessmenttype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'course', 'component');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('scan_assessment_idx', XMLDB_INDEX_NOTUNIQUE, ['scanid', 'assessmenttype']);
        if ($dbman->table_exists($table) && !$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('tool_cc_assessment');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scanid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assessmenttype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('objectname', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('existsflag', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('critical', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('warnings', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('notices', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ignored', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('score', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '100');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('scan_fk', XMLDB_KEY_FOREIGN, ['scanid'], 'tool_cc_scan', ['id']);
        $table->add_index('scan_type_idx', XMLDB_INDEX_NOTUNIQUE, ['scanid', 'assessmenttype']);
        $table->add_index('scan_course_idx', XMLDB_INDEX_NOTUNIQUE, ['scanid', 'courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('tool_cc_exception');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rulecode', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('objectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('profile_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'tool_cc_profile', ['id']);
        $table->add_index('profile_course_idx', XMLDB_INDEX_NOTUNIQUE, ['profileid', 'courseid']);
        $table->add_index('rule_idx', XMLDB_INDEX_NOTUNIQUE, ['rulecode']);
        $table->add_index('enabled_idx', XMLDB_INDEX_NOTUNIQUE, ['enabled']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $now = time();
        foreach (['credit', 'practice'] as $kind) {
            $name = get_string('profilepreset_' . $kind, 'tool_coursecompliance');
            if (!$DB->record_exists('tool_cc_profile', ['name' => $name])) {
                $DB->insert_record('tool_cc_profile', (object)[
                    'name' => $name,
                    'description' => get_string('profilepreset_' . $kind . '_desc', 'tool_coursecompliance'),
                    'enabled' => 1,
                    'configjson' => \tool_coursecompliance\profile_config::encode(
                        \tool_coursecompliance\profile_config::preset($kind)
                    ),
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026090804, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026090806) {
        // Normalize course-level result rows and use a meaningful XMLDB default.
        $table = new xmldb_table('tool_cc_result');
        $field = new xmldb_field(
            'assessmenttype',
            XMLDB_TYPE_CHAR,
            '16',
            null,
            XMLDB_NOTNULL,
            null,
            'course',
            'component'
        );
        if ($dbman->table_exists($table) && $dbman->field_exists($table, $field)) {
            $DB->set_field('tool_cc_result', 'assessmenttype', 'course', ['assessmenttype' => '']);
            $dbman->change_field_default($table, $field);
        }

        // Idnumber is always explicitly supplied by the assessment scanner, so no default is required.
        $table = new xmldb_table('tool_cc_assessment');
        $field = new xmldb_field(
            'idnumber',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            XMLDB_NOTNULL,
            null,
            null,
            'objectname'
        );
        if ($dbman->table_exists($table) && $dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090806, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026090900) {
        $renames = [
            'tool_cc_profile' => 'tool_coursecompliance_profile',
            'tool_cc_scan' => 'tool_coursecompliance_scan',
            'tool_cc_result' => 'tool_coursecompliance_result',
            'tool_cc_act_batch' => 'tool_coursecompliance_act_batch',
            'tool_cc_act_change' => 'tool_coursecompliance_act_change',
            'tool_cc_session_issue' => 'tool_coursecompliance_session_issue',
            'tool_cc_assessment' => 'tool_coursecompliance_assessment',
            'tool_cc_exception' => 'tool_coursecompliance_exception',
        ];
        foreach ($renames as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            $newtable = new xmldb_table($newname);
            if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
                $dbman->rename_table($oldtable, $newname);
            }
        }

        upgrade_plugin_savepoint(true, 2026090900, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026090901) {
        // Code-style-only release; no database changes are required.
        upgrade_plugin_savepoint(true, 2026090901, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091000) {
        // Blueprint snapshots are stored in profile config JSON; no database schema changes are required.
        upgrade_plugin_savepoint(true, 2026091000, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091001) {
        // Runtime form loading and profile-selection UX update; no database changes are required.
        upgrade_plugin_savepoint(true, 2026091001, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091002) {
        // Align the built-in credit profile with the institutional final-assessment idnumber "exam".
        $profiles = $DB->get_records('tool_coursecompliance_profile');
        foreach ($profiles as $profile) {
            $config = \tool_coursecompliance\profile_config::decode($profile->configjson);
            $iscreditpreset = in_array(
                $profile->name,
                ['Зачёт — базовый стандарт', 'Credit — base standard'],
                true
            );
            if ($iscreditpreset && ($config['examidnumber'] ?? '') === 'credit') {
                $config['examidnumber'] = 'exam';
                $profile->configjson = \tool_coursecompliance\profile_config::encode($config);
                $profile->timemodified = time();
                $DB->update_record('tool_coursecompliance_profile', $profile);
            }
        }

        upgrade_plugin_savepoint(true, 2026091002, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091003) {
        // Remove the obsolete built-in Credit profile while preserving its audit history.
        $examprofile = null;
        foreach (['Экзамен — базовый стандарт', 'Exam — base standard'] as $examname) {
            $examprofile = $DB->get_record('tool_coursecompliance_profile', ['name' => $examname]);
            if ($examprofile) {
                break;
            }
        }

        if (!$examprofile) {
            $now = time();
            $examprofile = (object)[
                'name' => get_string('profilepreset_exam', 'tool_coursecompliance'),
                'description' => get_string('profilepreset_exam_desc', 'tool_coursecompliance'),
                'enabled' => 1,
                'configjson' => \tool_coursecompliance\profile_config::encode(
                    \tool_coursecompliance\profile_config::preset('exam')
                ),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $examprofile->id = $DB->insert_record('tool_coursecompliance_profile', $examprofile);
        }

        foreach (['Зачёт — базовый стандарт', 'Credit — base standard'] as $creditname) {
            $creditprofiles = $DB->get_records('tool_coursecompliance_profile', ['name' => $creditname]);
            foreach ($creditprofiles as $creditprofile) {
                $DB->set_field(
                    'tool_coursecompliance_scan',
                    'profileid',
                    $examprofile->id,
                    ['profileid' => $creditprofile->id]
                );
                $DB->set_field(
                    'tool_coursecompliance_exception',
                    'profileid',
                    $examprofile->id,
                    ['profileid' => $creditprofile->id]
                );
                $DB->delete_records('tool_coursecompliance_profile', ['id' => $creditprofile->id]);
            }
        }

        upgrade_plugin_savepoint(true, 2026091003, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091100) {
        // Blueprint Engine 0.6 stores its expanded rules inside profile config JSON.
        upgrade_plugin_savepoint(true, 2026091100, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091101) {
        // CI-only maintenance release; no database schema changes are required.
        upgrade_plugin_savepoint(true, 2026091101, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091102) {
        // Close malformed queued scans created without a selected profile in earlier releases.
        $message = get_string('scaninvalidprofile', 'tool_coursecompliance');
        $now = time();
        $DB->execute(
            "UPDATE {tool_coursecompliance_scan}
                SET status = :status, finishedat = :finishedat, errormessage = :errormessage
              WHERE profileid = :profileid AND status = :queuedstatus",
            [
                'status' => 'failed',
                'finishedat' => $now,
                'errormessage' => $message,
                'profileid' => 0,
                'queuedstatus' => 'queued',
            ]
        );

        upgrade_plugin_savepoint(true, 2026091102, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091103) {
        // Activity browser normalization fix; no database schema changes are required.
        upgrade_plugin_savepoint(true, 2026091103, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091104) {
        // Native Quiz review-options layout; configuration remains JSON-compatible.
        upgrade_plugin_savepoint(true, 2026091104, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091105) {
        // Native availability editor for exam/resit Blueprint rules; no schema change required.
        upgrade_plugin_savepoint(true, 2026091105, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091106) {
        // Information architecture, navigation and report visualisation update; no schema change required.
        upgrade_plugin_savepoint(true, 2026091106, 'tool', 'coursecompliance');
    }

    if ($oldversion < 2026091110) {
        // Background operation status, report filters and safe auto-fix use the existing batch schema.
        upgrade_plugin_savepoint(true, 2026091110, 'tool', 'coursecompliance');
    }


    if ($oldversion < 2026091400) {
        // Version 0.9 adds profile-driven native availability editing and safe remediation.
        // No database schema changes are required.
        upgrade_plugin_savepoint(true, 2026091400, 'tool', 'coursecompliance');
    }

    return true;
}
