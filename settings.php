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
 * Administration settings for the plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$root = new admin_category(
    'tool_coursecompliance_root',
    new lang_string('plugincategory', 'tool_coursecompliance')
);
$ADMIN->add('courses', $root);

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance',
    get_string('nav_dashboard', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/index.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance_actions',
    get_string('nav_actions', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/action_center.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance_readiness',
    get_string('nav_audit', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/readiness.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance_questions',
    get_string('nav_questions', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/question_bank.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance_profiles',
    get_string('nav_standards', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/profiles.php'),
    'tool/coursecompliance:manageprofiles'
));

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance_session',
    get_string('nav_session', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/session.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
    'tool_coursecompliance_activities',
    get_string('nav_operations', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/activities.php'),
    'tool/coursecompliance:view'
));

if ($hassiteconfig) {
    $ADMIN->add('tool_coursecompliance_root', new admin_externalpage(
        'tool_coursecompliance_settings',
        get_string('nav_settings', 'tool_coursecompliance'),
        new moodle_url('/admin/tool/coursecompliance/plugin_settings.php'),
        'moodle/site:config'
    ));
}
