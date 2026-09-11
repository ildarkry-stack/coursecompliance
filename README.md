# Course Compliance Center (`tool_coursecompliance`)

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

