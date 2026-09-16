# Contributing

Thank you for improving Course Compliance Center.

## Development baseline

The plugin must remain compatible with Moodle 5.0 through 5.2. New code should not use APIs introduced after Moodle 5.0 unless a compatibility path is provided and covered by the compatibility matrix.

Changes to SQL must remain portable across PostgreSQL and MySQL. Administrative write actions must validate capabilities and `sesskey`, and must not silently guess educational content or ambiguous course structure.

## Automated checks

GitHub Actions installs the plugin on supported Moodle/PHP combinations and runs the release-quality checks. When reproducing them locally, use the actual Moodle path for Moodle-dependent commands.

Typical checks are:

```bash
moodle-plugin-ci phplint <plugin>
moodle-plugin-ci phpcs --max-warnings 0 <plugin>
moodle-plugin-ci validate --moodle <moodle> <plugin>
moodle-plugin-ci savepoints <plugin>
moodle-plugin-ci phpdoc --moodle <moodle> --max-warnings 0 <plugin>
moodle-plugin-ci mustache --moodle <moodle> <plugin>
moodle-plugin-ci phpunit --moodle <moodle> --fail-on-warning <plugin>
```

For AMD JavaScript, run Moodle's own Grunt from the Moodle root because the plugin checkout may be outside the Moodle source tree:

```bash
cd <moodle>
npx grunt amd --root=<installed plugin path relative to Moodle root>
```

For Moodle 5.0 the installed plugin path is normally `admin/tool/coursecompliance`; for Moodle 5.1+ it is normally `public/admin/tool/coursecompliance`.

## Pull requests

A change should include regression tests when it affects scanning, current-state aggregation, Question Bank inspection, Privacy API behaviour, Safe AutoFix, rollback, batch scheduling, database upgrades or release packaging.

Use Moodle APIs instead of direct writes when a suitable API exists. Do not add automatic remediation that invents question content, chooses among ambiguous activities, or overwrites a value that changed after preview.

## Security reports

Do not open a public issue for a suspected vulnerability. Follow `SECURITY.md` and avoid including credentials, student data or production database dumps in reports.
