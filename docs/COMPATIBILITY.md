# Compatibility policy

Course Compliance Center is developed as one codebase for Moodle 5.0, 5.1 and 5.2.

## Supported Moodle versions

| Moodle | Minimum PHP | CI coverage | Status |
| --- | --- | --- | --- |
| 5.0 | 8.2 | PHP 8.2 and 8.4, PostgreSQL and MySQL smoke coverage | Supported (legacy branch) |
| 5.1 | 8.2 | PHP 8.2 and 8.4, PostgreSQL and MySQL smoke coverage | Supported |
| 5.2 | 8.3 | PHP 8.3 and 8.4, PostgreSQL and MySQL smoke coverage | Supported |

The plugin deliberately does not claim support for a Moodle version until the GitHub Actions compatibility matrix installs that Moodle branch and runs the PHPUnit suite successfully.

## Database policy

The primary CI database is PostgreSQL 16 because it satisfies the requirements of Moodle 5.0 through 5.2. MySQL 8.4 compatibility is also exercised for each supported Moodle branch.

## API policy

CourseCompliance uses Moodle public APIs where possible. The compatibility test suite explicitly checks the core APIs that the plugin relies on for:

- output hooks and course indicators;
- Quiz structure and grading;
- question-bank inspection and random-question pools;
- AdHoc task processing;
- Moodle Messaging.

No filesystem path is hard-coded to Moodle 5.1+'s `public/` directory. URLs use Moodle-root paths such as `/admin/tool/coursecompliance/...`, allowing the same plugin package to work with Moodle 5.0 and the routed public directory used by later branches.

## Future Moodle releases

A new Moodle branch is not added to `$plugin->supported` until clean install, static analysis and PHPUnit are green on that branch. This avoids silently advertising compatibility with APIs that have not been tested yet.

## Manual installation paths

The repository layout changed in Moodle 5.1. For manual source-tree installation use `admin/tool/coursecompliance` on Moodle 5.0 and `public/admin/tool/coursecompliance` on Moodle 5.1/5.2. Installing the ZIP through the Moodle administrator interface avoids this distinction.

## Runtime diagnostics

From 0.19.0-rc1, site administrators can open **CourseCompliance → Settings → Diagnostics** to compare the running Moodle, PHP and database environment with this compatibility policy and to inspect CourseCompliance background-work health. The page is read-only and is intended to make support reports reproducible.
