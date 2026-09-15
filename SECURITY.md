# Security policy

## Supported versions

Security fixes are provided for the latest CourseCompliance beta/stable release on the Moodle versions listed in `docs/COMPATIBILITY.md`.

## Reporting a vulnerability

Please report suspected vulnerabilities privately to the plugin maintainers before opening a public issue. Include:

- CourseCompliance version;
- Moodle and PHP versions;
- affected capability or workflow;
- reproduction steps;
- expected and actual result;
- whether the issue allows data disclosure, privilege escalation or unintended bulk changes.

Do not include production credentials, student data or database dumps in public reports.

## Security design

CourseCompliance uses Moodle capabilities and `sesskey` protection for administrative actions. Bulk remediation is previewed before execution, re-checks expected old values before writing, runs through Moodle background tasks and records change history. Content-level decisions such as inventing correct answers or selecting between ambiguous duplicate activities are intentionally not automated.
