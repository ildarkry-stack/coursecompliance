# Security policy

## Supported versions

Security fixes are provided for the latest CourseCompliance release-candidate/stable line on the Moodle versions listed in `docs/COMPATIBILITY.md`.

## Reporting a vulnerability

Please report suspected vulnerabilities privately to the plugin maintainers before opening a public issue. Include:

- CourseCompliance release;
- Moodle and PHP versions;
- affected capability or workflow;
- reproduction steps;
- expected and actual result;
- whether the issue permits data disclosure, privilege escalation or unintended bulk changes.

Do not include production credentials, student data or database dumps in public reports.

## Security design

CourseCompliance uses Moodle capabilities and `sesskey` protection for administrative actions. Bulk remediation is previewed before execution, re-checks expected old values before writing, runs through Moodle background tasks and records change history. Content-level decisions such as inventing correct answers or selecting between ambiguous duplicate activities are intentionally not automated.

The plugin does not require an external CourseCompliance service or API credential. Scan-completion notifications are sent through Moodle's core Messaging subsystem.
