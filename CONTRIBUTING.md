# Contributing

Thank you for improving Course Compliance Center.

## Development baseline

The plugin must remain compatible with Moodle 5.0 through 5.2. New code should not use APIs introduced after Moodle 5.0 unless a compatibility path is provided.

Before opening a pull request, run the same checks used by GitHub Actions:

```bash
moodle-plugin-ci phplint <plugin>
moodle-plugin-ci phpcs --max-warnings 0 <plugin>
moodle-plugin-ci validate <plugin>
moodle-plugin-ci savepoints <plugin>
moodle-plugin-ci phpdoc --max-warnings 0 <plugin>
moodle-plugin-ci mustache <plugin>
moodle-plugin-ci grunt --max-lint-warnings 0 <plugin>
moodle-plugin-ci phpunit --fail-on-warning <plugin>
```

## Pull requests

A change should include regression tests when it affects scanning, current-state aggregation, Question Bank inspection, Safe AutoFix, rollback, batch scheduling or database upgrades.

Avoid direct SQL writes when a Moodle API exists. Never add automatic remediation that guesses educational content or silently resolves ambiguous course structures.
