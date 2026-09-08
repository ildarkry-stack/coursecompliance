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

namespace tool_coursecompliance\form;

/**
 * Filter form for centralized activity operations.
 *
 * @package    tool_coursecompliance
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_filter_form extends \moodleform {
    /** @inheritdoc */
    protected function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];

        $categories = \core_course_category::make_categories_list();
        $mform->addElement('autocomplete', 'categoryid', get_string('category', 'tool_coursecompliance'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        $courses = [0 => get_string('allcourses', 'tool_coursecompliance')] + ($custom['courses'] ?? []);
        $mform->addElement('autocomplete', 'courseid', get_string('course'), $courses);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('select', 'modname', get_string('activitytype', 'tool_coursecompliance'), [
            'all' => get_string('all', 'tool_coursecompliance'),
            'quiz' => get_string('modulename', 'quiz'),
            'assign' => get_string('modulename', 'assign'),
            'forum' => get_string('modulename', 'forum'),
        ]);
        $mform->setType('modname', PARAM_ALPHA);

        $sections = [-1 => get_string('allsections', 'tool_coursecompliance')] + ($custom['sections'] ?? []);
        $mform->addElement('select', 'sectionnum', get_string('section', 'tool_coursecompliance'), $sections);
        $mform->setType('sectionnum', PARAM_INT);

        $mform->addElement('text', 'search', get_string('activitysearch', 'tool_coursecompliance'));
        $mform->setType('search', PARAM_TEXT);

        $mform->addElement('select', 'datestate', get_string('datestate', 'tool_coursecompliance'), [
            'all' => get_string('all', 'tool_coursecompliance'),
            'hasdates' => get_string('datestate_hasdates', 'tool_coursecompliance'),
            'nodates' => get_string('datestate_nodates', 'tool_coursecompliance'),
            'upcoming' => get_string('datestate_upcoming', 'tool_coursecompliance'),
            'overdue' => get_string('datestate_overdue', 'tool_coursecompliance'),
        ]);
        $mform->setType('datestate', PARAM_ALPHA);

        $mform->addElement('select', 'visibility', get_string('visibility', 'tool_coursecompliance'), [
            'all' => get_string('all', 'tool_coursecompliance'),
            'show' => get_string('visibility_show', 'tool_coursecompliance'),
            'hide' => get_string('visibility_hide', 'tool_coursecompliance'),
            'stealth' => get_string('visibility_stealth', 'tool_coursecompliance'),
        ]);
        $mform->setType('visibility', PARAM_ALPHA);

        $mform->addElement('select', 'restrictions', get_string('restrictions', 'tool_coursecompliance'), [
            'all' => get_string('all', 'tool_coursecompliance'),
            'configured' => get_string('restrictions_configured', 'tool_coursecompliance'),
            'none' => get_string('restrictions_none', 'tool_coursecompliance'),
        ]);
        $mform->setType('restrictions', PARAM_ALPHA);

        $mform->addElement('select', 'perpage', get_string('perpage', 'tool_coursecompliance'), [
            25 => 25,
            50 => 50,
            100 => 100,
            200 => 200,
        ]);
        $mform->setType('perpage', PARAM_INT);

        $this->add_action_buttons(false, get_string('filter', 'tool_coursecompliance'));
    }
}
