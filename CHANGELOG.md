# Changelog

## 0.18.0-beta — 2026-09-15

- Added automatic retry for failed scan workers with a conservative default of one retry and a configurable 0–3 retry limit.
- Added manual **Retry failed batches** recovery that preserves successful worker results and requeues only failed packages.
- Added per-worker performance telemetry: execution time, database query count, peak PHP memory and retry count. Parent audits aggregate these metrics for capacity planning.
- Added performance cards to completed audit reports and failed-worker diagnostics to failed audit reports.
- Added regression tests for worker retry recovery, finalizer metric aggregation and performance measurements.
- Added schema fields for scan performance/reliability telemetry; no existing audit/result data is removed.

## 0.17.0-beta — 2026-09-15

- Declared one supported codebase for Moodle 5.0, 5.1 and 5.2.
- Lowered the minimum Moodle requirement to 5.0 and promoted the plugin maturity to beta.
- Added a GitHub Actions compatibility matrix covering supported PHP ranges on PostgreSQL 16 plus MySQL 8.4 smoke coverage for every supported Moodle branch.
- Split release-quality static checks from cross-version PHPUnit jobs to keep CI useful without repeating expensive lint jobs for every matrix entry.
- Added cross-version API regression tests for output hooks, Quiz grading, Question Bank inspection, AdHoc tasks and Moodle Messaging.
- Added compatibility, security, contribution and release-readiness documentation in preparation for public distribution.
- No database schema changes are introduced in 0.17.

## 0.16.0-alpha — 2026-09-15

- Added scalable parent/worker scan orchestration. Large selected audits are split into independent Moodle AdHoc tasks instead of one long-running scanner task.
- Added scan aggregation: worker results are merged into the parent audit only after every batch finishes, keeping reports and current-state logic consistent.
- Added configurable worker size (5/10/20/30/50 courses; default 10) and live batch progress on the scan report.
- Added Moodle Messaging notifications when an audit completes or fails.
- Added SQL-backed Action Center pagination for normal severity/search browsing so large issue lists are not rendered in one request.
- Prevented internal worker scans from appearing as current audits, dashboard history or course readiness state.
- Removed the largest Question Bank N+1 hotspot by preloading answer rows, multiple-choice options and matching pairs in bulk and avoiding full question-object loads for supported core types.
- Split question-bank integrity rules into a dedicated registry and extracted course-scope loading/scheduling/notification responsibilities from the scanner path.
- Added regression coverage for batching, stale Action Center findings and real Moodle Quiz question types (`multichoice`, `match`, `shortanswer`).

## 0.15.0-alpha — 2026-09-15

- Fixed stale Action Center findings: the current problem list now follows the newest completed check for each course/profile pair, including manually selected partial audits. A clean newer audit removes obsolete findings from older category-wide scans.
- Prevented duplicate current issues caused by the same course being included in scans of different parent categories.
- Replaced numeric course limits with an explicit staged course picker. Administrators choose a category, then select the exact courses to audit.
- Added course search, Select all / Clear / first 5 / 10 / 20 helpers for large categories.
- Added the latest audit date and current readiness status next to every course before a scan is launched.

## 0.14.0-alpha — 2026-09-15

- Reworked correct-answer integrity checks to avoid false critical findings for valid shuffled `multichoice` and `match` questions.
- Added type-aware validation for core multiple choice, matching, short-answer, numerical and true/false questions.
- Unsupported automatically graded question types are now reported as a partial-check notice instead of a critical error.
- Kept Question Bank inside the **Audit & reports** navigation context when opened from that section.
- Reworked Blueprint Quiz review settings to use Moodle's native period-oriented checkbox-group layout and dependencies.
- Added an in-plugin Settings page so administrators keep the Course Compliance navigation while changing plugin configuration.
- Site administration now links Course Compliance Settings to the same integrated settings page.

## 0.13.0-alpha (2026-09-14)

- Added persistent **per-question diagnostics** for Quiz/question-bank audit findings. Aggregate errors such as “41 questions without a valid correct answer” can now be expanded to the exact questions involved.
- Added a dedicated **Question bank quality** workspace with filters by severity, exam/resit period, issue type, question type and free-text search.
- Detailed rows store the question id, type, version status, bank category, Quiz slot and structured diagnostic details when available.
- Added direct actions to open the native Moodle question editor, the course question bank and the Quiz structure.
- Correct-answer diagnostics now distinguish an empty correct response, a response that does not receive full credit, and question types whose correct response cannot be fully validated without attempt/variant state.
- Added question-level details for broken questions, non-Ready versions, zero-mark slots, duplicate bank content, unused Ready questions and random-pool failures.
- Action Center, course-problem reports and detailed scan reports now expose a “Show questions” action when question-level evidence exists.
- Question content remains read-only to CourseCompliance: the plugin never rewrites question wording or correct answers automatically.
- Added a new detailed-question issue table and retention/privacy cleanup support. Existing scans remain valid; run a fresh audit to populate question-level diagnostics.

## 0.12.0-alpha (2026-09-14)

- Added an **Action Center** that consolidates current critical issues, warnings, newly introduced problems and Safe AutoFix candidates across standards and course categories.
- Added multi-select Safe AutoFix from the Action Center; selected fixes still use immutable preview, conflict detection, background execution, history and rollback.
- Safe AutoFix now automatically queues a targeted recheck of only the courses actually changed, and current readiness views overlay those recheck results on the last full audit.
- Added a **Readiness matrix** with Course, Exam, Resit 1, Resit 2, Question bank, Access restrictions, Gradebook and Completion domains.
- Added a **Pre-session check** that separates courses ready for the session from courses with blockers and explains each blocker.
- Added per-course weighted readiness snapshots (critical issues carry a larger penalty than warnings/notices) and course readiness history.
- Added scan deltas: new issues, resolved issues, courses that became ready and courses that lost readiness. The dashboard now includes a “What needs attention now” view.
- Extended question-bank diagnostics with duplicate-content detection and questions present in the dedicated bank category but unused by the target Quiz. Existing random-pool, draft, broken-reference and correct-answer checks remain available.
- Added Exam-to-Resit structure comparison for question count, maximum grade and question-bank category source.
- Added Gradebook integrity checks for missing/orphaned/duplicate Quiz grade items and hidden Quiz grade items. Safe AutoFix can resynchronise an incorrect Quiz grade-item maximum through Moodle APIs.
- Added course-completion integrity checks for missing criteria and criteria pointing to missing activities.
- Existing access-restriction auditing is surfaced as a dedicated readiness-matrix domain; safe restriction fixes remain conservative and require unambiguous exam/resit references.
- Full-audit dashboards are no longer replaced by small test runs or targeted rechecks; current state is built from the last full audit plus newer affected-course rechecks.

## 0.11.0-alpha (2026-09-14)

- Added deep Quiz integrity checks using Moodle question-bank APIs.
- Detects empty quizzes, broken question references, empty/insufficient random pools, draft questions, missing correct responses for automatically graded questions, and mark-distribution mismatches.
- Reports question-bank category spread for exam/resit quizzes.
- Correct-answer checks that require attempt/variant state are reported as partial instead of falsely flagging valid questions as broken.
- Added per-standard controls for question-bank integrity checks.
- Added safe auto-fix for explicit grade-to-pass standards; existing safe warning fixes for attempts, time limits, Quiz grade, review visibility, browser security and answer shuffling remain available.
- Added selectable audit size: 1, 5, 10, 20, 50, 100 or all courses.

## 0.10.0-alpha

- Refreshed the plugin visual system across administration pages with consistent navigation, cards, forms, tables and spacing.
- Reworked Course standards into cleaner profile cards and removed letter/number prefixes from standard-rule sections.
- Added a dashboard course-readiness report showing ready courses versus courses requiring attention.
- Added semantic green/red readiness charting and a readiness percentage for the latest completed audit.
- Simplified Blueprint wording and page hierarchy for faster administration.

## 0.9.3-alpha

- Replaced the unreliable server-rendered course banner placement with a navigation-triggered AMD status panel.
- Site administrators now see coloured compliance banners immediately on the course home page: red for critical issues, amber for warnings, blue for notices, and green when the course is fully ready.
- A clean audited course is now distinguished from a course that has never been audited.
- The course navigation shows a ready state when the latest applicable audits contain no problems.
- The status panel links directly to the course-specific compliance report and shows the latest audit time/profile.

## 0.9.2-alpha

- Fixed the administrator course warning placement: the banner is moved into the visible course content area.
- Course indicators now aggregate the latest applicable scan for each enabled profile instead of letting an unrelated newer scan hide problems.
- Added a dedicated administrator page with the current problems for one course.

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
