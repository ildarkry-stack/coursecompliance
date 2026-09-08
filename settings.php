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

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance',
    get_string('pluginname', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/index.php'),
    'tool/coursecompliance:view'
));


$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance_readiness',
    get_string('sessionreadiness', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/readiness.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance_profiles',
    get_string('profiles', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/profiles.php'),
    'tool/coursecompliance:manageprofiles'
));

$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance_exceptions',
    get_string('exceptions', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/exceptions.php'),
    'tool/coursecompliance:manageprofiles'
));

$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance_session',
    get_string('sessionplanning', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/session.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance_activities',
    get_string('activityoperations', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/activities.php'),
    'tool/coursecompliance:view'
));

$ADMIN->add('courses', new admin_externalpage(
    'tool_coursecompliance_activityhistory',
    get_string('operationhistory', 'tool_coursecompliance'),
    new moodle_url('/admin/tool/coursecompliance/activity_history.php'),
    'tool/coursecompliance:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_coursecompliance_settings', get_string('settings', 'tool_coursecompliance'));
    $settings->add(new admin_setting_configtext(
        'tool_coursecompliance/retentiondays',
        get_string('retentiondays', 'tool_coursecompliance'),
        get_string('retentiondays_desc', 'tool_coursecompliance'),
        90,
        PARAM_INT
    ));
    $categoryoptions = [0 => get_string('notselected', 'tool_coursecompliance')]
        + \core_course_category::make_categories_list();
    $settings->add(new admin_setting_configselect(
        'tool_coursecompliance/sessiondefaultcategory',
        get_string('sessiondefaultcategory', 'tool_coursecompliance'),
        get_string('sessiondefaultcategory_desc', 'tool_coursecompliance'),
        0,
        $categoryoptions
    ));
    $ADMIN->add('tools', $settings);
}
