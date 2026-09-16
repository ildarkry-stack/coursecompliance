# Moodle Marketplace preparation

CourseCompliance 0.19.0-rc1 is the release-candidate line used to prepare the first public stable release.

## Recommended Marketplace metadata

**Plugin name**  
Course Compliance Center

**Frankenstyle component**  
`tool_coursecompliance`

**Plugin type**  
Administration tool

**Short description**  
Audit Moodle courses against reusable standards, inspect assessment and Question Bank readiness, and safely remediate deterministic configuration issues across large course collections.

**Long description**  
Course Compliance Center gives Moodle administrators one workspace for course-quality audits, assessment readiness and controlled bulk remediation. Administrators can define reusable standards, capture a reference Course Blueprint, audit Exam/Resit targets, Question Bank integrity, gradebook, completion and access restrictions, and review current issues in an Action Center. Large audits are split into Moodle background workers with retry and performance telemetry. Deterministic fixes and activity operations use preview, optimistic conflict detection, background execution, history and safe rollback. The plugin intentionally does not invent educational content, guess correct answers or silently resolve ambiguous course structures.

**Supported Moodle versions**  
5.0, 5.1, 5.2

**License**  
GNU GPL v3 or later

**External service requirement**  
None

**Paid subscription requirement**  
None

## Suggested tags

- course quality
- compliance
- audit
- assessment
- quiz
- question bank
- administration
- bulk management
- readiness

## Assets to prepare in Marketplace

Before submission, capture current screenshots from a clean Moodle site using this release candidate. Recommended screenshots:

1. Overview dashboard with readiness cards and charts.
2. Audit course picker and current status.
3. Detailed audit report with expected/actual values.
4. Action Center.
5. Question Bank quality diagnostics.
6. Course Standard / Blueprint editor.
7. Activity Operations preview.
8. Settings → Diagnostics.

Do not use production student names, emails, course data or credentials in screenshots.

## Public project metadata still required outside the ZIP

Before submitting the plugin, ensure the public project record has:

- a public source-code repository;
- a public issue tracker;
- a documentation URL;
- a maintainer/support contact path;
- release tags matching `$plugin->release`;
- screenshots and release notes.

GitHub issue templates are included in the repository to make the issue tracker useful for bug reports and feature requests.

## 0.19.0-rc1 release notes

- Added an administrator Diagnostics page showing the plugin release, Moodle/PHP/database compatibility and CourseCompliance background-work health.
- Completed Privacy API user-list support and declared use of Moodle Messaging.
- Added Privacy API regression tests for user discovery and selective bulk deletion.
- Scoped all remaining audit-course-picker CSS selectors to CourseCompliance pages.
- Added Marketplace, privacy and release-candidate QA documentation.
- Added structured GitHub issue and pull-request templates.
- Added release-package validation to CI.
- No database schema changes are introduced by this release candidate.

## Before 1.0

The release candidate should complete the QA plan in `QA.md`, including a representative 100-course scan, clean ZIP installation, upgrade from 0.18, privacy export/deletion validation and manual accessibility review. Only then should the release string and maturity be promoted to 1.0.0 stable.
