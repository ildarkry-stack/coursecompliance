# Course Compliance Center (`tool_coursecompliance`)

Course Compliance Center is a Moodle administration tool for auditing course quality, assessment readiness and institutional standards across large course collections. It combines read-only diagnostics with conservative, preview-first remediation workflows for settings that have one unambiguous target value.

**Release:** 1.1.0-rc1
**Supported Moodle versions:** 5.0, 5.1 and 5.2
**Supported databases in CI:** PostgreSQL and MySQL
**License:** GNU GPL v3 or later

## Main capabilities

- Run compliance audits for selected courses in a category and its subcategories.
- Define reusable standards for course settings, Exam, Resit 1 and Resit 2.
- Capture a reference Course Blueprint and selectively enforce its rules.
- Audit Quiz structure, Question Bank integrity, gradebook, completion and access restrictions.
- Show current problems in Action Center without allowing stale historical scans to override newer clean checks.
- Show per-course readiness, readiness matrix, pre-session checks and course history.
- Schedule assessment date changes for Quiz and Assignment activities across many courses.
- Browse and change supported activities through preview, conflict detection, background apply, history and safe rollback.
- Apply Safe AutoFix only to deterministic settings; CourseCompliance never invents educational content or chooses between ambiguous targets.
- Split large audits into Moodle AdHoc worker tasks with retry, recovery and performance telemetry.
- Notify the initiating administrator through Moodle Messaging when a scan completes or fails.
- Provide a read-only Diagnostics page for supported Moodle/PHP/database checks and background-processing health.

## Safety model

CourseCompliance is intentionally conservative. Bulk changes are prepared as a preview before execution. The apply stage checks that the value being changed still matches the value seen during preview. If another administrator or teacher changed the same setting meanwhile, the conflicting change is skipped instead of overwriting newer work.

Question content is read-only. The plugin does not create questions, guess correct answers, silently select duplicate activities, invent missing assessment dates or resolve ambiguous access dependencies automatically.

Administrative write actions require Moodle capabilities and `sesskey` protection. Long-running work is performed through Moodle background tasks.

## Requirements

CourseCompliance supports a single codebase for Moodle 5.0 through 5.2.

| Moodle | Minimum PHP used by this release | Tested database families |
| --- | --- | --- |
| 5.0 | PHP 8.2 | PostgreSQL, MySQL |
| 5.1 | PHP 8.2 | PostgreSQL, MySQL |
| 5.2 | PHP 8.3 | PostgreSQL, MySQL |

The plugin uses core Quiz, Question Bank, Availability, Messaging and AdHoc task APIs. Moodle cron/background task processing must be working for audits, Safe AutoFix and confirmed bulk operations.

See [`docs/COMPATIBILITY.md`](docs/COMPATIBILITY.md) for the compatibility policy.

## Installation

Install the ZIP through **Site administration → Plugins → Install plugins**, or copy the `coursecompliance` directory into the administration-tool directory for the Moodle source layout in use:

- Moodle 5.0: `admin/tool/coursecompliance`
- Moodle 5.1 and 5.2: `public/admin/tool/coursecompliance`

Then complete the normal Moodle upgrade. No external service, subscription, API key or Composer runtime dependency is required.

## First use

1. Open **Site administration → Courses → Course quality control**.
2. Review or create a Course Standard.
3. Choose **Audit and reports**, select the standard and category, then load and select the courses to check.
4. Let Moodle cron process the queued scan tasks.
5. Review the resulting report, Action Center and readiness views.
6. Use Safe AutoFix or Activity Operations only after reviewing the generated preview.

For large sites, start with a small scope and increase gradually. See [`docs/PERFORMANCE.md`](docs/PERFORMANCE.md).

## Diagnostics and support information

Site administrators can open **CourseCompliance → Settings → Diagnostics**. The page reports the CourseCompliance release, detected Moodle version, PHP version, database family, required API availability, pending CourseCompliance background tasks, failed parent audits, long-running audits and the last completed audit. It is read-only and does not change site configuration.

When reporting a problem, include the CourseCompliance release, Moodle/PHP versions, database family and the relevant audit ID. Do not attach credentials, student data or production database dumps.

## Privacy

CourseCompliance stores administrator-related operational metadata for audit runs, bulk activity operations and documented compliance exceptions. It does not store student submissions or answers. Scan-completion notifications use Moodle's core Messaging subsystem.

The plugin implements Moodle's Privacy API, including metadata declaration, export, single-user deletion and bulk user-list deletion in the system context. See [`docs/PRIVACY.md`](docs/PRIVACY.md).

## Development and automated checks

The release gate requires Moodle coding standards and PHPUnit across Moodle 5.0, 5.1 and 5.2, with PostgreSQL and MySQL. This ZIP does not include evidence that the matrix has passed for 1.1.0-rc1. Release packaging is also validated to contain a single top-level `coursecompliance/` directory without repository metadata.

See [`CONTRIBUTING.md`](CONTRIBUTING.md), [`SECURITY.md`](SECURITY.md) and [`docs/RELEASE_CHECKLIST.md`](docs/RELEASE_CHECKLIST.md).

## Release status

1.1.0-rc1 addresses audit publication, report memory use, task recovery and immutable standards.
This build has not been executed inside Moodle in the authoring environment. Clean install,
upgrade, PHPUnit and the compatibility matrix must pass before promotion to stable.
See `docs/VALIDATION-1.1.md` for the exact checks and remaining limitations.

## Marketplace preparation

Ready-to-copy listing text, release metadata and the remaining submission checklist are in [`docs/MARKETPLACE.md`](docs/MARKETPLACE.md). The plugin does not require a paid service and does not communicate with an external CourseCompliance service.