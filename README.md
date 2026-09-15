# Course Compliance Center (`tool_coursecompliance`)

## 0.16 Architecture & Scale

Version 0.16 changes how large audits execute without changing the administrator workflow. A selected set of courses is now divided into independent Moodle AdHoc worker batches and aggregated into one parent report. The default worker size is 10 courses and can be changed in CourseCompliance settings. This reduces peak memory, limits the impact of a single heavy course and makes audits of hundreds of courses practical with multiple Moodle task workers.

The scan report shows completed batch progress, while Moodle Messaging notifies the initiating administrator when the parent audit completes or fails. Internal worker scans are deliberately excluded from dashboards, readiness views and current-problem calculations.

Question-bank validation now preloads answer records, multiple-choice options and matching pairs in bulk instead of issuing per-question queries for supported core question types. The scanner path has also been decomposed into scheduling, scope-loading, notification and question-integrity rule services. Action Center uses SQL pagination for its normal issue list, and new regression tests cover stale-current-state handling, batched scans and valid real Moodle Quiz questions.

## 0.13 Question bank problem analysis

Version 0.13 focuses on moving from aggregate Quiz warnings to actionable question-level diagnostics. The **Question bank quality** page shows the exact questions behind current audit findings, including their question id, type, bank category, version status and the reason the check failed. Aggregate report rows link directly to these details.

The audit distinguishes real content problems from checks that Moodle cannot safely complete without attempt/variant state. It records broken question definitions, questions without a resolvable full-credit response, non-Ready versions, zero-mark Quiz slots, duplicate content in the dedicated bank category, unused Ready questions and empty/insufficient random pools. Administrators can jump to the native Moodle question editor, question bank or Quiz structure.

CourseCompliance intentionally keeps question content **read-only**: it does not invent or rewrite correct answers. This makes the question-bank workflow diagnostic and safe while still reducing the time needed to find the exact item that must be corrected.


## 0.12 operational quality workflow

Version 0.12 turns the audit into an operational workflow rather than a static report.

- **Action Center** shows the current critical issues, warnings, new issues and Safe AutoFix candidates across the latest applicable standards.
- **Targeted recheck** automatically scans only courses changed by Safe AutoFix and overlays those results on the last full audit, so readiness can turn green without rescanning an entire faculty.
- **Readiness matrix** shows each course against Course settings, Exam, Resit 1, Resit 2, Question bank, Access restrictions, Gradebook and Completion.
- **Pre-session check** produces a practical Ready / Not ready list with blockers for required exam and resit activities.
- **Course history** records readiness score changes alongside batch-operation history.
- **Weighted readiness** penalises critical problems more heavily than warnings and notices; a percentage never hides the presence of a critical issue.
- **Delta reporting** highlights new and resolved issues plus courses that became ready or lost readiness.
- **Question-bank quality** additionally detects duplicate question content and questions left unused in the dedicated bank category, while retaining empty Quiz, random-pool, draft, broken-reference and correct-answer diagnostics.
- **Assessment consistency** can compare resits with the exam for question count, maximum grade and source question-bank categories.
- **Gradebook and completion integrity** detect missing/orphaned/duplicate Quiz grade items, hidden Quiz grade items and broken or absent completion criteria.

Safe AutoFix remains deliberately conservative. It can repair only settings for which the configured standard gives one unambiguous target value. It does not invent questions, select between duplicate activities, rewrite educational content or guess which bank category should be used.


## 0.10 interface refresh

The administration UI uses a consistent visual system across dashboard, standards, reports, session planning and activity operations. The dashboard includes course readiness (ready vs. needs attention), while the standards editor uses semantic sections instead of letter/number prefixes.

Administrative quality-control, assessment readiness and safe bulk-operations toolkit for **Moodle 5.1** and large installations.

## 0.3.0-alpha

### Course / Assessment audit

- Category + subcategory scanning in an adhoc task.
- Compliance profiles (default profile installed automatically).
- Course checks: start date, date consistency, completion, directly assigned teacher.
- Assessment/Quiz checks for `exam`, `resit1`, `resit2` idnumbers.
- Quiz checks: open/close dates, date consistency, attempts, time limit, grade, question slots, grade-to-pass.
- Persistent scan results and severity filtering.

### Session Planning

A specialized workflow inspired by the operational need previously handled by `local_examdates`, but implemented on the shared Course Compliance batch engine.

- Select a course category and optionally include subcategories.
- Configure Exam, Resit 1 and Resit 2 independently.
- Resolve Quiz and Assignment targets by course-module `idnumber`.
- Set one opening/start and closing/due window across hundreds or thousands of courses.
- Missing targets are reported instead of silently ignored.
- Duplicate idnumbers are treated as conflicts and skipped instead of selecting an arbitrary activity.
- Assignment `cutoffdate` and `gradingduedate` can be moved forward when required to remain valid after a due-date change; disabled secondary dates remain disabled.
- Exact immutable preview before any write.
- The same optimistic conflict detection, adhoc background apply, audit history and safe rollback used by Activity Operations.
- Legacy `local_examdates` may remain installed during migration; its historical log is never rewritten or imported as rollbackable history.

### Activity Operations

Original centralized administration workflow for supported activities (`quiz`, `assign`, `forum`):

- Browse activities across an entire category and all subcategories.
- Filter by course, section, activity type, text/idnumber, date state, visibility and access-restriction state.
- Edit activity dates from one table, including Assignment grading due date.
- Bulk-shift configured dates forward/backward by hours, days, weeks or months.
- Bulk Show / Hide / Stealth visibility operations.
- Bulk name normalization using find/replace.
- Exact preview before every write.
- Per-activity optimistic conflict detection before apply.
- Adhoc background execution.
- Per-change audit history.
- Safe rollback preview for applied batches; newer teacher/admin edits are detected and excluded.
- Quiz date changes refresh calendar events, the grade item, open-attempt state and preview attempts.

### Deliberately not included

- Permanent bulk deletion.
- Direct arbitrary editing of Moodle availability JSON.
- Automatic rollback of historical operations from `local_examdates`.

Install to `admin/tool/coursecompliance`. Moodle cron must be running for audits and confirmed batches.

## 0.4 Assessment quality and session readiness

Version 0.4 adds configurable compliance profiles and a deeper Quiz Assessment Auditor. A profile can define expected assessment idnumbers, question-count ranges, attempts, time limits, maximum grade, grade-to-pass policy, review-option safety, password/IP/browser-security expectations and override limits.

The scanner stores a per-assessment readiness snapshot for `exam`, `resit1` and `resit2`. The **Session readiness** page therefore reads completed scan data instead of re-scanning courses in the web request, and shows ready/warning/critical/missing counts plus a readiness score.

Known legitimate deviations can be recorded as profile-specific exceptions with a reason and optional expiry date. Exceptions only affect subsequent scans; previous audit history remains unchanged.

This release remains conservative: the advanced auditor is read-only. Automatic remediation of assessment settings is intentionally deferred until the audit rules have been validated against real Moodle 5.1 course data.

## 0.5 Course Blueprint and detailed reports

Version 0.5 adds a reusable **Course Blueprint** snapshot. An administrator can select a well-configured
course from the profile list, capture it, and then audit other courses against the stored snapshot without
reading the live template again on every scan.

The first blueprint comparison covers:

- course format, completion tracking and group mode settings;
- named section presence/position;
- required activities identified by stable course-module `idnumber` and their module type;
- normalized activity access restrictions (cross-course course-module and grade-item IDs are translated to idnumbers);
- activity completion settings;
- calculated grade formulas normalized to grade-item idnumbers;
- course completion criteria.

Profiles can keep the existing manual assessment rules and add the blueprint checks in hybrid mode. Re-importing
the source course creates a new explicit blueprint version; previous scans are not retroactively changed.

The compliance report is also expanded for administrators. It now includes a dedicated **What is missing**
summary, affected-course counts, expected versus actual values, the exact problem location, remediation guidance,
direct edit links into Moodle and filtered links from Assessment Auditor to the relevant course/assessment problems.
## 0.6 Blueprint rule constructor

Version 0.6 turns the template course into a selective institutional standard. Importing a course stores an immutable snapshot; the **Blueprint checks** page then lets an administrator decide exactly which values are enforced and whether each value comes from the template or is entered manually.

The constructor covers course settings, content counts and required pages, independent `exam` / `resit1` / `resit2` checks, a visual Quiz review-options matrix, access restrictions, activity completion, course-grade formula/max grade and course-completion conditions. Access restrictions for `exam`, `resit1` and `resit2` are edited through Moodle's native availability interface; Grade and Activity completion dependencies are limited to those three stable assessment idnumbers. The detailed report explains what is missing, what differs, expected/actual values and the recommended place to fix the problem.

## 0.7 Information architecture and visual reports

Version 0.7 reorganises the plugin as a single Course quality control workspace under **Site administration → Courses**. The admin tree now exposes only the main destinations: Overview, Audit and reports, Course standards, Session planning, Activity operations and Settings. Exceptions and operation history remain available as secondary tabs rather than separate top-level entries.

The standalone Assessment Auditor is merged into the Audit and reports page. Existing links to `assessment.php` are preserved through a compatibility redirect. Reports now use Moodle's native chart API for severity distribution, missing-vs-misconfigured requirements, exam/resit readiness and recent audit trends. Metric cards, badges and shared navigation make the main workflow easier to scan without removing the underlying audit detail.

Blueprint pages also avoid raw technical values where possible: stored access restrictions and activity completion settings are displayed in human-readable form, while low-frequency technical fields are moved under Moodle's advanced form controls.


## 0.8 Background operations, filters and Safe Auto-fix

Version 0.8 makes long-running changes easier to understand and introduces conservative remediation for problems that have one unambiguous target value.

- Queued and running batches show a clear status card, processed-change counters and start/finish timestamps.
- Completed operations show summary cards for processed courses, changed/skipped/failed activities and, for session scheduling, unresolved targets and targets that already matched the requested dates.
- The detailed audit report can be filtered by severity, missing vs incorrect settings, auto-fixable problems, issue-free courses, assessment period and course-name search.
- **Safe Auto-fix** never writes immediately. It first creates the same immutable preview batch used by Activity Operations, then uses conflict detection, background execution, history and safe rollback.
- Supported automatic corrections include explicit Quiz standards for attempts, time limit, maximum grade, answer shuffling, browser security and review-option visibility. Blueprint mode can also correct explicit values for the same settings plus Quiz visibility.
- Content-level decisions remain manual: the plugin does not create quizzes, add or remove questions, choose passwords/subnets, invent missing dates, resolve duplicate idnumbers or guess which missing activity should be used.

### Administrator course indicator

Site administrators see a compliance warning on the course home page when the latest applicable completed audit contains critical issues, warnings, or notices. A matching course-navigation item links directly to the problems for that course. Other users do not see the indicator.


## Quiz integrity and question bank audit

Version 0.11 adds read-only validation through Moodle question-bank APIs. For exam, resit1 and resit2 it can detect empty tests, unresolved/broken question references, empty random-question pools, insufficient random pools, draft question versions, automatically graded questions without a resolvable correct response, and mismatches between the sum of question marks and the Quiz maximum grade.

The audit also records the question-bank category spread for each target. When an automatically graded question type needs attempt or variant state before its correct response can be evaluated, CourseCompliance reports the deep check as partial rather than treating the question as broken.

Audit launches can be limited to 1, 5, 10, 20, 50 or 100 courses for staged testing, or run against all courses for a complete readiness result.


## 0.15 Current-state reports and staged course audits

The Action Center now resolves the newest completed check for every course/profile pair instead of treating an older category-wide scan as permanently authoritative. This means a newer clean audit of one course immediately removes obsolete findings from the current problem list, while historical scan reports remain available as historical snapshots.

Audit launch is now staged for large installations: choose a standard and category, load its course tree, then select the exact courses to inspect. The picker shows each course's latest check date and readiness status and provides search plus quick selection controls for the first 5, 10 or 20 courses, all visible courses, or none. Only the selected course ids are queued in the background scan.
