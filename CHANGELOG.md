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
