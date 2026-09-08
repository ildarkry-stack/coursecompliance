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
