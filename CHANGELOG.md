# Changelog

## 0.9.0-alpha — 2026-09-14

- Uses Moodle's native Restrict access editor for exam/resit profile rules; raw JSON is not exposed to administrators.
- Completion and grade dependency selectors are limited to the configured Exam, Resit 1 and Resit 2 targets and show those friendly labels directly.
- Stores availability rules portably by assessment idnumber and resolves them to course-specific IDs only when editing/applying.
- Adds safe auto-fix for Blueprint assessment access restrictions when every referenced exam/resit/group/grouping can be resolved unambiguously.
- Automatically queues a fresh compliance scan after a successful safe auto-fix batch and links the new report from the operation page.


## 0.7.2-alpha

- Replaced automatic chart colours with a consistent semantic palette across reports.
- Ready is green, warnings are amber, critical issues are red, missing items are grey, and informational notices are blue.
- Missing requirements and configuration mismatches now use red and orange respectively in the problem-kind chart.

## 0.7.1-alpha

- Reworked Session planning date-change preview and result pages into a course-by-period report inspired by the exam-date workflow.
- Each course now appears once with dedicated Exam, Resit 1 and Resit 2 columns.
- Old date windows are shown above the new date windows with clear visual emphasis.
- Missing, duplicate and inaccessible targets are shown inline in the relevant period cell.
- Added compact preview/operation statistics with target counts and direct access to skipped/error details.
- Quiz and Assignment targets can be displayed together without falling back to one-row-per-field technical output.

## 0.7.0-alpha

- Moved all user-facing pages into a dedicated **Course quality control** subsection under Site administration → Courses.
- Reduced admin-tree clutter: Exceptions and Activity operation history now live as secondary tabs inside Standards and Activity Operations.
- Merged the standalone Assessment Auditor into the **Audit and reports** workspace; the old route remains as a compatibility redirect.
- Added a consistent primary navigation bar and context-specific secondary navigation across the plugin.
- Added native Moodle charts to the dashboard, detailed compliance report, and session-readiness report.
- Added visual metric cards and status/severity badges for faster scanning of results.
- Reworked the dashboard around the main workflow: run audit → inspect readiness → open detailed report.
- Improved Blueprint presentation: captured access restrictions and completion rules are now shown in readable form instead of raw JSON.
- Moved low-frequency technical Blueprint fields into Moodle's advanced controls to reduce form clutter.
- Removed the legacy `local_examdates` notice and obsolete duplicated UI strings.
- Kept internal workflow pages (preview/apply/rollback, Blueprint editors, exception creation, session issues) out of the admin tree while preserving all functionality.

## 0.6.0-alpha

- Reworked Course Blueprint into an explicit A–D rule constructor instead of comparing every captured property.
- Added course setting checks for activity dates, upload limit, completion tracking and group mode.
- Added course-content checks for regular Quiz counts, Page counts, Page-name keyword search, required literature pages with aliases, section names and optional module-type counts.
- Added independent `exam`, `resit1` and `resit2` rule panels with grade-to-pass, grade, attempts, time limit, grading method, answer shuffling, group mode, visibility, restrictions, completion, dates, password, subnet, browser security and question checks.
- Added a visual Quiz review-options matrix for all four review periods.
- Added course-grade maximum and calculated-grade formula checks.
- Added course completion criteria comparison.
- Added duplicate activity idnumber detection and richer administrator remediation messages.
- Blueprint snapshots now include full supported activity inventory, Quiz settings, course grade metadata and stable group/grouping references.
- Session Readiness is populated from Blueprint assessment checks when the visual Blueprint engine is active.
- No new database tables are required; existing profiles, scans, exceptions and batch history are preserved.


## 0.5.3-alpha

- Removed the built-in Credit profile. New installations now create only Exam and Practice presets.
- Existing built-in Credit profiles are removed on upgrade; their scans and exceptions are reassigned to the built-in Exam profile to preserve history.

## 0.5.2-alpha

- Changed the built-in Credit profile to use the institutional final-assessment `idnumber` `exam` instead of `credit`.
- Added an upgrade migration for existing built-in Credit profiles while leaving custom profiles untouched.
- Added a regression test for the Credit preset.

## 0.5.0-alpha - 2026-09-10

- Added Course Blueprint snapshots imported from a selected template course.
- Added hybrid profile mode: template-derived rules plus manually configured rules.
- Added blueprint comparison for course settings, named sections, required activities by idnumber,
  access restrictions, activity completion, calculated grade formulas, and course completion criteria.
- Added an administrator-friendly detailed report with a dedicated “What is missing” summary,
  expected/actual values, exact problem location, direct Moodle edit links, and remediation guidance.
- Added per-assessment links from Assessment Auditor to the filtered detailed problem report.
- Blueprint snapshots are immutable until an administrator explicitly imports the source course again.

## 0.4.4-alpha-ci-fixed - 2026-09-09

- Restored the required `MOODLE_INTERNAL` guard in form classes that include `formslib.php`.
- Fixes Moodle CodeSniffer `MoodleInternalGlobalState` errors.

# Changelog

## 0.4.3-alpha — CI validation and coding-style hardening

- Renamed plugin database tables to the required `tool_coursecompliance_*` prefix and added a safe upgrade rename step from the earlier `tool_cc_*` tables.
- Fixed PHPDoc parameter lists reported by Moodle PHPDoc Checker.
- Sorted English and Russian language string keys according to Moodle coding style.
- Reworked `rule_registry.php` and remaining multiline calls/control structures to Moodle/PSR formatting.
- Replaced disallowed `elseif` usages with `else if`.
- Removed unnecessary `MOODLE_INTERNAL` checks from autoloaded class files and `db/install.php`.
- Fixed privacy provider formatting, member-variable PHPDoc, form/task method documentation and whitespace warnings.
- Kept fresh installs and upgrades from 0.4.2 and earlier on the same data model.


## 0.4.1-alpha — CI hardening

- Added Moodle-compliant file, class and method PHPDoc where required.
- Restored the complete GPL header in `activity_batch.php`.
- Reworked long PHP lines to stay within the Moodle 132-character guideline.
- Normalized anonymous function and arrow-function spacing.
- Made GitHub Actions resolve either repository-root or nested `coursecompliance/` layouts.
- Pinned the CI toolchain to the maintained Moodle Plugin CI 4.5 line and skipped plugin Node setup.

## 0.4.0-alpha (2026-09-08)

- Added editable compliance profiles with Exam, Credit and Practice presets.
- Added profile rules for question counts, zero-mark slots, Quiz/gradebook grade consistency, grade-to-pass, time limits and attempts.
- Added exam safety checks for review options, password, subnet, browser security and answer shuffling.
- Added user/group override auditing with configurable attempt and close-time extension limits.
- Added profile-specific, expiring compliance exceptions created directly from audit findings.
- Added persistent per-assessment readiness snapshots and a Session Readiness dashboard.
- Added a dedicated Assessment Auditor view for exam/resit readiness.
- Added ignored-by-exception counters to scans.
- Added PHPUnit coverage for profile defaults, assessment safety rules and session planning preview construction.
- Added GitHub Actions workflow for Moodle Plugin CI against Moodle 5.1 / PHP 8.3 and 8.4.


## 0.3.0-alpha — 2026-09-08

- Added **Session Planning** for Exam / Resit 1 / Resit 2 across course categories.
- Added Quiz and Assignment target resolution by course-module idnumber.
- Session Planning now uses the shared Course Compliance preview → conflict check → adhoc apply → audit → safe rollback engine instead of a second update engine.
- Added explicit missing-target, duplicate-idnumber and permission issue reporting.
- Removed the manual 200-activity limitation from the session-planning workflow.
- Added Assignment `gradingduedate` to the shared timeline model and Activity Operations UI.
- Added optional automatic correction of enabled Assignment cut-off and grading-due dates when the due date moves beyond them.
- Added paginated change previews for very large batches.
- Added a category-level `tool/coursecompliance:scheduleassessments` capability.
- Added upgrade support from 0.2.1-alpha.
- Legacy `local_examdates` can coexist during migration; its historical log remains independent and is not made rollbackable by the new engine.

## 0.2.1-alpha — 2026-09-08

- Added safe Activity Operations rollback previews.
- Rollback only includes values that still match the source batch output, preventing overwrites of newer manual edits.
- Added source-batch linkage to operation history.
- Kept destructive bulk deletion and arbitrary availability-JSON editing out of scope.

## 0.2.0-alpha — 2026-09-08

- Added **Activity Operations**, a new original multi-course activity administration module.
- Added category/course/section/type/search/date/visibility/restriction-state filters.
- Added centralized date editing for Quiz, Assignment and Forum.
- Added bulk relative date shift, visibility changes and activity-name normalization.
- Added preview batches, optimistic conflict detection, adhoc apply task and operation history.
- Added module-specific Quiz side effects when dates change (calendar, grade item, open attempts, preview attempts).
- Added upgrade path from 0.1.0-alpha and GDPR/retention handling for operation history.
- Destructive deletion and arbitrary restriction editing remain intentionally excluded.

## 0.1.0-alpha — 2026-09-08

- Initial Course Compliance and Quiz Assessment auditor.

## 0.6.4-alpha-native-quiz-review
- Reworked Blueprint Quiz review options to mirror the Moodle 5.1 native period-oriented UI.
- Uses the same core labels and ordering for the eight review options.
- Added native dependencies between Attempt, Maximum marks, Marks and feedback options.
- Enforces Moodle core fixed states for the "During the attempt" period.

## 0.6.5-alpha-native-availability

- Replaced Blueprint access-restriction JSON editing with Moodle 5.1's native `core_availability` editor.
- Added one native restriction editor for each `exam`, `resit1` and `resit2` target.
- Limited Grade and Activity completion dependencies to the configured assessment targets in both the UI and server-side validation.
- Stored course-specific course-module and grade-item IDs as portable assessment idnumbers so Blueprint rules can be reused across courses.
- Kept native Date, User profile, Restriction set and other enabled Moodle availability conditions available.
- Rebuilt the EN/RU language files from the last intact baseline to repair previously truncated multi-line strings.


## 0.8.0-alpha - 2026-09-11

- Added clear background operation states with progress counters for queued/running/completed batches.
- Added post-operation summary cards for processed courses, changed/skipped/failed activities, and session targets that were already correct or unresolved.
- Expanded audit report filters: severity, missing vs incorrect settings, auto-fixable issues, ready courses, assessment period, and course search.
- Added conservative Safe Auto-fix. It prepares a preview batch for unambiguous Quiz setting corrections and uses the same conflict checks, history, background worker and rollback path as other activity operations.
- Safe Auto-fix never creates quizzes or questions and never guesses passwords, subnets, or missing dates.

## 0.9.1-alpha - 2026-09-14

- Added an administrator-only compliance warning on the course home page when the latest applicable audit contains problems.
- Added a course-navigation problem indicator linking directly to the filtered audit report for that course.
- Indicators always use the newest completed audit applicable to the course category hierarchy, so older findings are not shown after a clean re-scan.
- Fixed Moodle CodeSniffer whitespace issues in the availability blueprint test and upgrade script.
