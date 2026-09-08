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

require_once(__DIR__ . '/../../../config.php');
require_login();
$systemcontext = context_system::instance();
require_capability('tool/coursecompliance:view', $systemcontext);

$categories = \core_course_category::make_categories_list();
$categoryid = optional_param('categoryid', 0, PARAM_INT);
if (!$categoryid && $categories) {
    $categoryid = (int)array_key_first($categories);
}
$courseid = optional_param('courseid', 0, PARAM_INT);
$modname = optional_param('modname', 'all', PARAM_ALPHA);
$sectionnum = optional_param('sectionnum', -1, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$datestate = optional_param('datestate', 'all', PARAM_ALPHA);
$visibility = optional_param('visibility', 'all', PARAM_ALPHA);
$restrictions = optional_param('restrictions', 'all', PARAM_ALPHA);
$perpage = optional_param('perpage', 50, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = in_array($perpage, [25, 50, 100, 200], true) ? $perpage : 50;

$coursemenu = [];
$sectionmenu = [];
if ($categoryid) {
    $category = \core_course_category::get($categoryid, MUST_EXIST, true);
    $categoryids = array_merge([$category->id], $category->get_all_children_ids());
    [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'menucat');
    $coursemenu = $DB->get_records_select_menu('course', "category $catsql AND id <> :siteid", $catparams + ['siteid' => SITEID], 'fullname ASC', 'id, fullname');
    if ($courseid && isset($coursemenu[$courseid])) {
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC', 'id,section,name');
        foreach ($sections as $section) {
            $sectionmenu[(int)$section->section] = $section->name !== null && $section->name !== ''
                ? format_string($section->name)
                : get_string('sectionnum', 'tool_coursecompliance', $section->section);
        }
    }
}

$filters = [
    'categoryid' => $categoryid,
    'courseid' => $courseid,
    'modname' => $modname,
    'sectionnum' => $sectionnum,
    'search' => $search,
    'datestate' => $datestate,
    'visibility' => $visibility,
    'restrictions' => $restrictions,
    'perpage' => $perpage,
];

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/admin/tool/coursecompliance/activities.php', $filters + ['page' => $page]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('activityoperations', 'tool_coursecompliance'));
$PAGE->set_heading(get_string('activityoperations', 'tool_coursecompliance'));

$form = new \tool_coursecompliance\form\activity_filter_form(
    new moodle_url('/admin/tool/coursecompliance/activities.php'),
    ['courses' => $coursemenu, 'sections' => $sectionmenu],
    'get'
);
$form->set_data((object)$filters);

$repository = new \tool_coursecompliance\activity_repository();
$total = $categoryid ? $repository->count($filters) : 0;
$activities = $categoryid ? $repository->get($filters, $page * $perpage, $perpage) : [];
$canmanage = has_capability('tool/coursecompliance:manageactivities', $systemcontext);

/**
 * Render a date field for the activity operations table.
 *
 * @param int $cmid Course module id.
 * @param string $field Activity date field.
 * @param int $timestamp Current Unix timestamp.
 * @param bool $editable Whether the field can be edited.
 * @return string Rendered HTML.
 */
function tool_coursecompliance_date_input(int $cmid, string $field, int $timestamp, bool $editable): string {
    $value = \tool_coursecompliance\local\activity\date_helper::to_input_value($timestamp);
    if (!$editable) {
        return $timestamp ? userdate($timestamp) : '—';
    }
    return html_writer::empty_tag('input', [
        'type' => 'datetime-local',
        'name' => 'dates[' . $cmid . '_' . $field . ']',
        'value' => $value,
        'class' => 'form-control form-control-sm',
    ]);
}

/**
 * Render one normalized activity date cell.
 *
 * @param \stdClass $activity Normalized activity record.
 * @param string $field Activity date field.
 * @param string $label Human-readable label.
 * @param bool $editable Whether the field can be edited.
 * @return string Rendered HTML.
 */
function tool_coursecompliance_activity_date_cell(\stdClass $activity, string $field, string $label, bool $editable): string {
    if (!array_key_exists($field, $activity->dates)) {
        return '—';
    }
    return html_writer::tag('small', s($label), ['class' => 'text-muted d-block']) .
        tool_coursecompliance_date_input($activity->cmid, $field, (int)$activity->dates[$field], $editable);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activityoperations', 'tool_coursecompliance'));
echo html_writer::div(get_string('activityoperations_intro', 'tool_coursecompliance'), 'alert alert-info');
$form->display();

echo html_writer::div(get_string('activitiesfound', 'tool_coursecompliance', $total), 'mb-3 fw-bold');
if (!$activities) {
    echo $OUTPUT->notification(get_string('noactivitiesfound', 'tool_coursecompliance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$action = new moodle_url('/admin/tool/coursecompliance/activity_prepare.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $action->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
foreach ($filters as $key => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    '',
    get_string('course'),
    get_string('activity', 'tool_coursecompliance'),
    get_string('section', 'tool_coursecompliance'),
    get_string('visibility', 'tool_coursecompliance'),
    get_string('dateprimary', 'tool_coursecompliance'),
    get_string('datesecondary', 'tool_coursecompliance'),
    get_string('datecutoff', 'tool_coursecompliance'),
    get_string('dategradingdue', 'tool_coursecompliance'),
    get_string('restrictions', 'tool_coursecompliance'),
    get_string('actions'),
];

foreach ($activities as $activity) {
    $context = context_module::instance($activity->cmid);
    $editable = $canmanage && has_capability('moodle/course:manageactivities', $context);
    $checkbox = $canmanage ? html_writer::empty_tag('input', [
        'type' => 'checkbox', 'name' => 'selected[]', 'value' => $activity->cmid,
    ]) : '';
    $courseurl = new moodle_url('/course/view.php', ['id' => $activity->courseid]);
    $activityurl = new moodle_url('/mod/' . $activity->modname . '/view.php', ['id' => $activity->cmid]);
    $editurl = new moodle_url('/course/modedit.php', ['update' => $activity->cmid, 'return' => 1]);
    $type = get_string('modulename', $activity->modname);
    $activitylabel = html_writer::link($activityurl, format_string($activity->activityname)) .
        html_writer::tag('small', s($type) . ($activity->idnumber ? ' · ' . s($activity->idnumber) : ''), ['class' => 'text-muted d-block']);
    $section = $activity->sectionname !== '' ? format_string($activity->sectionname) : get_string('sectionnum', 'tool_coursecompliance', $activity->sectionnum);
    $visibilitytext = get_string('visibility_' . $activity->visibilitystate, 'tool_coursecompliance');
    $restrictions = empty($activity->availability) ? get_string('none', 'tool_coursecompliance') : get_string('configured', 'tool_coursecompliance');

    if ($activity->modname === 'quiz') {
        $date1 = tool_coursecompliance_activity_date_cell($activity, 'timeopen', get_string('quizopen', 'tool_coursecompliance'), $editable);
        $date2 = tool_coursecompliance_activity_date_cell($activity, 'timeclose', get_string('quizclose', 'tool_coursecompliance'), $editable);
        $date3 = '—';
        $date4 = '—';
    } elseif ($activity->modname === 'assign') {
        $date1 = tool_coursecompliance_activity_date_cell($activity, 'allowsubmissionsfromdate', get_string('assignstart', 'tool_coursecompliance'), $editable);
        $date2 = tool_coursecompliance_activity_date_cell($activity, 'duedate', get_string('duedate', 'tool_coursecompliance'), $editable);
        $date3 = tool_coursecompliance_activity_date_cell($activity, 'cutoffdate', get_string('cutoffdate', 'tool_coursecompliance'), $editable);
        $date4 = tool_coursecompliance_activity_date_cell($activity, 'gradingduedate', get_string('gradingduedate', 'tool_coursecompliance'), $editable);
    } else {
        $date1 = '—';
        $date2 = tool_coursecompliance_activity_date_cell($activity, 'duedate', get_string('duedate', 'tool_coursecompliance'), $editable);
        $date3 = tool_coursecompliance_activity_date_cell($activity, 'cutoffdate', get_string('cutoffdate', 'tool_coursecompliance'), $editable);
        $date4 = '—';
    }

    $table->data[] = [
        $checkbox,
        html_writer::link($courseurl, format_string($activity->coursename)),
        $activitylabel,
        $section,
        s($visibilitytext),
        $date1,
        $date2,
        $date3,
        $date4,
        $restrictions,
        html_writer::link($editurl, get_string('openstandardsettings', 'tool_coursecompliance')),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/admin/tool/coursecompliance/activities.php', $filters));

if ($canmanage) {
    echo html_writer::start_div('card mt-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h3', get_string('bulkoperation', 'tool_coursecompliance'), ['class' => 'h5']);
    echo html_writer::div(get_string('selectrowsfirst', 'tool_coursecompliance'), 'text-muted mb-3');

    echo html_writer::start_div('row g-3');
    echo html_writer::start_div('col-md-3');
    echo html_writer::label(get_string('operation', 'tool_coursecompliance'), 'id_operation', true);
    echo html_writer::select([
        'save_dates' => get_string('operation_save_dates', 'tool_coursecompliance'),
        'shift_dates' => get_string('operation_shift_dates', 'tool_coursecompliance'),
        'visibility' => get_string('operation_visibility', 'tool_coursecompliance'),
        'rename' => get_string('operation_rename', 'tool_coursecompliance'),
    ], 'operation', 'save_dates', false, ['id' => 'id_operation', 'class' => 'form-select']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-2');
    echo html_writer::label(get_string('shiftamount', 'tool_coursecompliance'), 'id_shiftamount', true);
    echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'shiftamount', 'id' => 'id_shiftamount', 'value' => 1, 'class' => 'form-control']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-2');
    echo html_writer::label(get_string('shiftunit', 'tool_coursecompliance'), 'id_shiftunit', true);
    echo html_writer::select([
        'hour' => get_string('shiftunit_hour', 'tool_coursecompliance'),
        'day' => get_string('shiftunit_day', 'tool_coursecompliance'),
        'week' => get_string('shiftunit_week', 'tool_coursecompliance'),
        'month' => get_string('shiftunit_month', 'tool_coursecompliance'),
    ], 'shiftunit', 'day', false, ['id' => 'id_shiftunit', 'class' => 'form-select']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-2');
    echo html_writer::label(get_string('visibility', 'tool_coursecompliance'), 'id_visibilitystate', true);
    echo html_writer::select([
        'show' => get_string('visibility_show', 'tool_coursecompliance'),
        'hide' => get_string('visibility_hide', 'tool_coursecompliance'),
        'stealth' => get_string('visibility_stealth', 'tool_coursecompliance'),
    ], 'visibilitystate', 'show', false, ['id' => 'id_visibilitystate', 'class' => 'form-select']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-3');
    echo html_writer::label(get_string('findtext', 'tool_coursecompliance'), 'id_findtext', true);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'findtext', 'id' => 'id_findtext', 'class' => 'form-control']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-3');
    echo html_writer::label(get_string('replacetext', 'tool_coursecompliance'), 'id_replacetext', true);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'replacetext', 'id' => 'id_replacetext', 'class' => 'form-control']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::div(
        html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('previewchanges', 'tool_coursecompliance'),
            'class' => 'btn btn-primary mt-3',
        ]) . ' ' . html_writer::link(
            new moodle_url('/admin/tool/coursecompliance/activity_history.php'),
            get_string('operationhistory', 'tool_coursecompliance'),
            ['class' => 'btn btn-secondary mt-3']
        )
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
