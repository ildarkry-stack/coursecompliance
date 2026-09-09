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
 * English language strings for the plugin.
 *
 * @package    tool_coursecompliance
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesschecks'] = 'Access and browser security';
$string['activitiesfound'] = '{$a} activities found';
$string['activity'] = 'Activity';
$string['activitymissing'] = 'The activity no longer exists.';
$string['activityoperations'] = 'Activity Operations';
$string['activityoperations_intro'] = 'Centralized timeline and bulk operations for Quiz, Assignment and Forum ' .
    'activities across a ' .
    'course category. Changes are previewed, conflict-checked, logged and applied by an adhoc ' .
    'task.';
$string['activityoutsidescope'] = 'A selected activity is outside the category/course scope used to build this view.';
$string['activitysearch'] = 'Search activity, course or idnumber';
$string['activitytype'] = 'Activity type';
$string['actual'] = 'Actual';
$string['addprofile'] = 'Add profile';
$string['adjustassignmentdates'] = 'Keep Assignment secondary dates valid';
$string['adjustassignmentdates_help'] = 'If an enabled cut-off date or grading due date would become earlier ' .
    'than the new due date, ' .
    'move it forward to the new due date. Disabled secondary dates remain disabled.';
$string['after'] = 'After';
$string['all'] = 'All';
$string['allcourses'] = 'All courses in category and subcategories';
$string['allsections'] = 'All sections';
$string['applied'] = 'Applied';
$string['assessment'] = 'Assessment';
$string['assessmentauditor'] = 'Assessment Auditor';
$string['assessmentperiod'] = 'Assessment period';
$string['assessments'] = 'Assessments';
$string['assignidnumber'] = 'Assignment idnumber';
$string['assignstart'] = 'Submissions from';
$string['backtoactivities'] = 'Back to Activity Operations';
$string['backtobatch'] = 'Back to batch preview';
$string['backtosessionplanning'] = 'Back to session planning';
$string['batchhasnochanges'] = 'No activity values need to be changed in this batch. Review target issues if they exist.';
$string['batchnotpreview'] = 'This batch is no longer awaiting confirmation.';
$string['batchnotqueued'] = 'This batch is not queued for execution.';
$string['batchnotrollbackable'] = 'This batch cannot be rolled back.';
$string['batchowneronly'] = 'Only the user who created this preview can apply it.';
$string['batchqueued'] = 'The operation was queued. Moodle cron will apply it in the background.';
$string['batchrunning'] = 'This batch is queued or running. Moodle cron applies it in the background.';
$string['batchstatus_complete'] = 'Complete';
$string['batchstatus_failed'] = 'Failed';
$string['batchstatus_partial'] = 'Completed with skips/errors';
$string['batchstatus_preview'] = 'Preview';
$string['batchstatus_queued'] = 'Queued';
$string['batchstatus_running'] = 'Running';
$string['batchsummary_dates'] = 'Save edited activity dates.';
$string['batchsummary_rename'] = 'Replace “{$a->find}” with “{$a->replace}” in selected activity names.';
$string['batchsummary_rollback'] = 'Rollback batch #{$a->source}: {$a->eligible} change(s) eligible; {$a->conflicts} changed ' .
    'since the original operation and excluded.';
$string['batchsummary_session'] = 'Assessment session schedule prepared for {$a} courses.';
$string['batchsummary_shift'] = 'Shift configured dates by {$a->amount} {$a->unit}(s).';
$string['batchsummary_visibility'] = 'Set selected activities to: {$a}.';
$string['before'] = 'Before';
$string['bulkoperation'] = 'Bulk operation';
$string['category'] = 'Course category';
$string['categoryrequired'] = 'Select a course category.';
$string['changes'] = 'Changes';
$string['changestatus_applied'] = 'Applied';
$string['changestatus_failed'] = 'Failed';
$string['changestatus_pending'] = 'Pending';
$string['changestatus_skipped'] = 'Skipped';
$string['component'] = 'Area';
$string['configured'] = 'Configured';
$string['confirmapply'] = 'Apply this batch';
$string['conflictchanged'] = 'Skipped because this activity changed after the preview was created.';
$string['copyofprofile'] = 'Copy of {$a}';
$string['coursechecks'] = 'Course checks';
$string['coursecompliance:manageprofiles'] = 'Manage compliance profiles and exceptions';
$string['coursecompliance:scheduleassessments'] = 'Plan assessment dates across course categories';
$string['courseonlyprofile'] = 'Course checks only';
$string['courses'] = 'Courses';
$string['createexception'] = 'Create exception';
$string['critical'] = 'Critical';
$string['cutoffdate'] = 'Cut-off';
$string['dashboard'] = 'Course compliance dashboard';
$string['datecutoff'] = 'Cut-off';
$string['dategradingdue'] = 'Grading due';
$string['dateprimary'] = 'Open / start';
$string['datesecondary'] = 'Due / close';
$string['datestate'] = 'Date state';
$string['datestate_hasdates'] = 'Has at least one date';
$string['datestate_nodates'] = 'No dates configured';
$string['datestate_overdue'] = 'Primary due/close date is in the past';
$string['datestate_upcoming'] = 'Opens in the future';
$string['defaultprofile'] = 'Default exam course';
$string['defaultprofile_desc'] = 'Baseline profile for courses with an exam identified by the course module idnumber exam.';
$string['disabled'] = 'Disabled';
$string['duedate'] = 'Due';
$string['duplicateprofile'] = 'Duplicate';
$string['duplicateprofiletarget'] = 'The idnumber “{$a}” is already used by another assessment period in this profile.';
$string['duplicatesessiontargetconfig'] = 'The idnumber “{$a}” is assigned to more than one selected period ' .
    'for the same activity type.';
$string['editprofile'] = 'Edit profile';
$string['emptyactivityname'] = 'The name operation would make activity “{$a}” empty.';
$string['enabled'] = 'Enabled';
$string['examidnumber'] = 'Primary exam idnumber';
$string['exceptioncreated'] = 'Exception created. It will be applied on the next scan.';
$string['exceptiondeleted'] = 'Exception deleted.';
$string['exceptionexpires'] = 'Expires';
$string['exceptionreason'] = 'Reason';
$string['exceptions'] = 'Compliance exceptions';
$string['exceptionsintro'] = 'An exception suppresses one specific rule for a course (and, where applicable, a specific ' .
    'activity). Exceptions are profile-specific and may expire automatically.';
$string['expected'] = 'Expected';
$string['expectedattempts'] = 'Expected attempts';
$string['expectedattempts_help'] = 'Set to 0 to skip the exact-attempt-count check. Moodle uses 0 in a quiz to mean unlimited ' .
    'attempts, so a profile value of 0 means this rule is disabled.';
$string['expectedbrowsersecurity'] = 'Expected browser security value';
$string['expectedbrowsersecurity_help'] = 'Leave empty to skip this check. Enter the Moodle browsersecurity ' .
    'value required by your ' .
    'environment, for example the value used by your configured browser security / Safe Exam ' .
    'Browser rule.';
$string['expectedquizgrade'] = 'Expected maximum quiz grade';
$string['expectedtimelimit'] = 'Expected time limit';
$string['failed'] = 'Failed';
$string['field'] = 'Field';
$string['field_allowsubmissionsfromdate'] = 'Submissions from';
$string['field_cutoffdate'] = 'Cut-off date';
$string['field_duedate'] = 'Due date';
$string['field_gradingduedate'] = 'Assignment grading due date';
$string['field_name'] = 'Activity name';
$string['field_timeclose'] = 'Quiz close';
$string['field_timeopen'] = 'Quiz open';
$string['field_visibilitystate'] = 'Visibility';
$string['filter'] = 'Apply filters';
$string['findtext'] = 'Find text';
$string['findtextrequired'] = 'Enter text to find before using name normalization.';
$string['gradingduedate'] = 'Grading due date';
$string['hideanswerswhileopen'] = 'Hide right answers until the quiz closes';
$string['hidecorrectnesswhileopen'] = 'Hide correctness until the quiz closes';
$string['ignored'] = 'Ignored by exception';
$string['includesubcategories'] = 'Include subcategories';
$string['intro'] = 'Audit course configuration and assessment readiness. Activity Operations is available as a ' .
    'separate preview-first bulk administration module.';
$string['invalidassigncutoff'] = 'Assignment “{$a}” would have a cut-off date before its due date.';
$string['invalidassigndates'] = 'Assignment “{$a}” would be due before submissions are allowed.';
$string['invalidassigngradingdue'] = 'Assignment “{$a}” will have a grading due date earlier than its due date.';
$string['invaliddatetime'] = 'One of the entered dates is invalid.';
$string['invalidforumcutoff'] = 'Forum “{$a}” would have a cut-off date before its due date.';
$string['invalidoperation'] = 'Unsupported activity operation.';
$string['invalidperioddates'] = 'The close/due time must be later than the open/start time.';
$string['invalidquestionrange'] = 'Maximum questions must be 0 or greater than or equal to minimum questions.';
$string['invalidquizdates'] = 'Quiz “{$a}” would close at or before its opening date.';
$string['invalidvisibilitystate'] = 'Invalid visibility state.';
$string['legacyexamdatesdetected'] = 'The legacy local_examdates plugin is installed. Course Compliance does not modify or reuse ' .
    'its history; new session operations use the unified Course Compliance batch engine.';
$string['locked'] = 'Locked';
$string['maxoverrideattempts'] = 'Maximum attempts allowed by an override (0 = no limit)';
$string['maxoverrideextension'] = 'Maximum close-time extension allowed by an override';
$string['maxquestions'] = 'Maximum number of questions (0 = no maximum)';
$string['message'] = 'Message';
$string['mingradepass'] = 'Minimum grade to pass';
$string['minquestions'] = 'Minimum number of questions';
$string['missing'] = 'Missing';
$string['noactivitiesfound'] = 'No supported activities match these filters.';
$string['noactivitiesselected'] = 'No supported activities were selected.';
$string['nocompletedscans'] = 'No completed compliance scans are available yet.';
$string['nocoursesinscope'] = 'No courses were found in the selected scope.';
$string['none'] = 'None';
$string['nothingtochange'] = 'The selected operation would not change any values.';
$string['nothingtorollback'] = 'There are no changes that can be rolled back safely. The affected activities may have ' .
    'changed since this batch was applied.';
$string['notices'] = 'Notices';
$string['notselected'] = 'Not selected';
$string['openassessmentauditor'] = 'Open Assessment Auditor';
$string['openstandardsettings'] = 'Standard settings';
$string['operation'] = 'Operation';
$string['operation_rename'] = 'Normalize activity names (find/replace)';
$string['operation_rollback'] = 'Rollback previous batch';
$string['operation_save_dates'] = 'Save edited dates';
$string['operation_session_schedule'] = 'Plan assessment session dates';
$string['operation_shift_dates'] = 'Shift configured dates';
$string['operation_visibility'] = 'Change visibility';
$string['operationhistory'] = 'Activity operation history';
$string['operationpreview'] = 'Activity operation preview';
$string['overridechecks'] = 'User and group overrides';
$string['period_exam'] = 'Exam';
$string['period_resit1'] = 'Resit 1';
$string['period_resit2'] = 'Resit 2';
$string['periodclose'] = 'Close / due date';
$string['periodopen'] = 'Open / submissions from';
$string['perpage'] = 'Rows per page';
$string['pluginname'] = 'Course compliance center';
$string['positivevalueexpected'] = 'Values must not be negative.';
$string['preparerollback'] = 'Prepare safe rollback';
$string['present'] = 'Present';
$string['previewchanges'] = 'Preview changes';
$string['previewconflictnote'] = 'Before applying each activity, the plugin re-checks the values captured by this preview. If ' .
    'a teacher changed the activity after preview, that activity is skipped instead of ' .
    'overwritten.';
$string['privacy:metadata'] = 'The Course compliance center stores administrator audit metadata, detected configuration ' .
    'issues, and activity-operation history. It does not store student submissions or answers.';
$string['privacy:metadata:activitybatch'] = 'Activity operation batches';
$string['privacy:metadata:activitybatch:userid'] = 'User who created the activity operation';
$string['privacy:metadata:exception'] = 'Compliance exceptions created by administrators';
$string['privacy:metadata:exception:reason'] = 'The administrative reason recorded for the exception';
$string['privacy:metadata:exception:userid'] = 'The user who created the compliance exception';
$string['privacy:metadata:scan'] = 'Audit runs';
$string['privacy:metadata:timecreated'] = 'Time the audit was created';
$string['privacy:metadata:userid'] = 'User who started the audit';
$string['problem'] = 'Problem';
$string['profile'] = 'Compliance profile';
$string['profiledescription'] = 'Description';
$string['profilename'] = 'Profile name';
$string['profilepreset_credit'] = 'Credit — base standard';
$string['profilepreset_credit_desc'] = 'Base profile for courses using a credit/credit test target (idnumber: credit).';
$string['profilepreset_exam'] = 'Exam — base standard';
$string['profilepreset_exam_desc'] = 'Base profile for courses with an exam. Requires the exam target and applies conservative ' .
    'assessment safety checks.';
$string['profilepreset_practice'] = 'Practice — base standard';
$string['profilepreset_practice_desc'] = 'Course-level compliance profile for practice courses where a Quiz exam is not required.';
$string['profiles'] = 'Compliance profiles';
$string['profilesaved'] = 'Compliance profile saved.';
$string['profilesintro'] = 'Profiles define the expected course and assessment configuration. Different profiles can be ' .
    'used for exams, credits, practice courses or other course types.';
$string['profiletargets'] = 'Assessment targets';
$string['quizchecks'] = 'Quiz assessment checks';
$string['quizclose'] = 'Close';
$string['quizgradeitemlocked'] = 'The Quiz grade item is locked, so its dates were not changed.';
$string['quizidnumber'] = 'Quiz idnumber';
$string['quizopen'] = 'Open';
$string['readinesscontext'] = '{$a->category} · {$a->profile} · overall assessment readiness: {$a->score}%';
$string['readinessscore'] = 'Readiness';
$string['ready'] = 'Ready';
$string['recentscans'] = 'Recent audits';
$string['replacetext'] = 'Replace with';
$string['report'] = 'Compliance report';
$string['requirecompletion'] = 'Require course completion tracking';
$string['requireexam'] = 'Require primary exam/assessment';
$string['requiregradepass'] = 'Require grade to pass';
$string['requirepassword'] = 'Require quiz password';
$string['requireresit1'] = 'Require resit 1';
$string['requireresit2'] = 'Require resit 2';
$string['requireshuffleanswers'] = 'Require shuffled answers';
$string['requiresubnet'] = 'Require IP/subnet restriction';
$string['requireteacher'] = 'Require at least one teacher';
$string['resit1idnumber'] = 'Resit 1 idnumber';
$string['resit2idnumber'] = 'Resit 2 idnumber';
$string['restrictions'] = 'Access restrictions';
$string['restrictions_configured'] = 'Restrictions configured';
$string['restrictions_none'] = 'No restrictions';
$string['retentiondays'] = 'Audit retention (days)';
$string['retentiondays_desc'] = 'Completed audit data and activity-operation history older than this many days are deleted ' .
    'by a scheduled task.';
$string['reviewchecks'] = 'Review options';
$string['rollbackofrollback'] = 'A rollback batch cannot be used as the source of another rollback.';
$string['rollbackpreviewcreated'] = 'A rollback preview has been created. Review it before applying.';
$string['rule'] = 'Rule';
$string['scanfailed'] = 'The audit failed';
$string['scanqueued'] = 'Audit queued. Moodle cron will process it in the background.';
$string['scanrunning'] = 'The audit is queued or running. Refresh this page after cron has processed the task.';
$string['scheduleperiod'] = 'Update this period';
$string['section'] = 'Section';
$string['sectionnum'] = 'Section {$a}';
$string['selectionlimit'] = 'Select between 1 and 200 activities at a time.';
$string['selectperiodrequired'] = 'Select at least one assessment period.';
$string['selectrowsfirst'] = 'Select up to 200 rows on this page. The next screen will show an exact preview before ' .
    'anything is changed.';
$string['sessiondefaultcategory'] = 'Default session-planning category';
$string['sessiondefaultcategory_desc'] = 'Preselect this course category when opening Session Planning. ' .
    'During migration, the old ' .
    'local_examdates default category is used when available.';
$string['sessionissue_duplicate'] = 'More than one activity has this idnumber ({$a} matches). The target was skipped.';
$string['sessionissue_missing'] = 'Target activity was not found.';
$string['sessionissue_nopermission'] = 'The user does not have permission to manage this activity.';
$string['sessionissues'] = 'Target issues';
$string['sessionissuesnotice'] = '{$a} target issue(s) were found. They are not included in the change batch.';
$string['sessionplanning'] = 'Session planning';
$string['sessionplanning_intro'] = 'Plan exam and resit windows across all courses in a category. Targets are resolved by ' .
    'course-module idnumber, previewed without changing Moodle, then applied by the shared ' .
    'background batch engine with conflict detection, audit history and safe rollback.';
$string['sessionreadiness'] = 'Session readiness';
$string['sessiontargetrequired'] = 'Enter at least one Quiz or Assignment idnumber for this period.';
$string['settings'] = 'Course compliance settings';
$string['severity'] = 'Severity';
$string['severity_critical'] = 'Critical';
$string['severity_notice'] = 'Notice';
$string['severity_warning'] = 'Warning';
$string['shiftamount'] = 'Shift amount';
$string['shiftunit'] = 'Unit';
$string['shiftunit_day'] = 'day';
$string['shiftunit_hour'] = 'hour';
$string['shiftunit_month'] = 'month';
$string['shiftunit_week'] = 'week';
$string['shiftzeronotallowed'] = 'The date shift amount cannot be zero.';
$string['skipped'] = 'Skipped';
$string['sourcebatch'] = 'Source batch';
$string['startscan'] = 'Start audit';
$string['status'] = 'Status';
$string['status_complete'] = 'Complete';
$string['status_failed'] = 'Failed';
$string['status_queued'] = 'Queued';
$string['status_running'] = 'Running';
$string['stealthdisabled'] = 'Stealth activities are disabled in this Moodle site.';
$string['taskcleanup'] = 'Clean up old compliance audits';
$string['topassessmentproblems'] = 'Most frequent assessment problems';
$string['total'] = 'Total';
$string['unlimited'] = 'Unlimited';
$string['unlocked'] = 'Unlocked';
$string['v_course_completion'] = 'Course completion tracking is disabled.';
$string['v_course_dates'] = 'Course end date is not later than the start date.';
$string['v_course_startdate'] = 'Course start date is not configured.';
$string['v_course_teacher'] = 'No directly assigned teacher/editing teacher was found at course context.';
$string['v_quiz_attempts'] = 'Exam attempt limit differs from the profile.';
$string['v_quiz_browsersecurity'] = 'Browser security does not match the profile standard.';
$string['v_quiz_close'] = 'Quiz closing date is not configured.';
$string['v_quiz_correctness_open'] = 'Correctness can be shown immediately after an attempt or later while the Quiz is still open.';
$string['v_quiz_dates'] = 'Quiz closing date is not later than the opening date.';
$string['v_quiz_duplicate_target'] = 'Several Quiz activities use the same target idnumber in this course.';
$string['v_quiz_grade'] = 'Quiz maximum grade is zero or invalid.';
$string['v_quiz_grade_standard'] = 'Quiz maximum grade differs from the profile standard.';
$string['v_quiz_gradebook_max'] = 'Quiz maximum grade and gradebook maximum do not match.';
$string['v_quiz_gradeitem_locked'] = 'The Quiz gradebook item is locked.';
$string['v_quiz_gradeitem_missing'] = 'The Quiz gradebook item is missing.';
$string['v_quiz_gradepass'] = 'Grade to pass is not configured for the exam.';
$string['v_quiz_gradepass_invalid'] = 'Grade to pass is higher than the Quiz maximum grade.';
$string['v_quiz_gradepass_min'] = 'Grade to pass is lower than the profile minimum.';
$string['v_quiz_open'] = 'Quiz opening date is not configured.';
$string['v_quiz_override_attempts'] = 'An override permits more attempts than the profile allows.';
$string['v_quiz_override_close'] = 'An override extends the close time beyond the profile limit.';
$string['v_quiz_overrides'] = 'The Quiz has user or group overrides that should be reviewed.';
$string['v_quiz_password'] = 'The profile requires a Quiz password, but no password is configured.';
$string['v_quiz_questions'] = 'Quiz contains no question slots.';
$string['v_quiz_questions_max'] = 'The Quiz contains more questions than the profile allows.';
$string['v_quiz_questions_min'] = 'The Quiz contains fewer questions than the profile requires.';
$string['v_quiz_required'] = 'Required assessment with idnumber "{$a}" was not found.';
$string['v_quiz_rightanswer_open'] = 'Right answers can be shown immediately after an attempt or later while the Quiz is still ' .
    'open.';
$string['v_quiz_shuffleanswers'] = 'The profile requires shuffled answers, but shuffle answers is disabled.';
$string['v_quiz_subnet'] = 'The profile requires an IP/subnet restriction, but none is configured.';
$string['v_quiz_sumgrades'] = 'Quiz question total (sumgrades) is zero.';
$string['v_quiz_timelimit'] = 'Exam time limit is not configured.';
$string['v_quiz_timelimit_standard'] = 'Quiz time limit differs from the profile standard.';
$string['v_quiz_zero_marks'] = 'One or more Quiz slots have zero maximum marks.';
$string['view'] = 'View';
$string['viewreport'] = 'View report';
$string['viewsessionissues'] = 'View target issues';
$string['visibility'] = 'Visibility';
$string['visibility_hide'] = 'Hidden';
$string['visibility_show'] = 'Shown on course page';
$string['visibility_stealth'] = 'Available but not shown on course page';
$string['warnings'] = 'Warnings';
$string['warnoverrides'] = 'Report user/group overrides';
