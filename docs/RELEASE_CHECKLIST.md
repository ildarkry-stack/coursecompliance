# Release checklist

## Automated gates

- [ ] Clean install on Moodle 5.0 / PHP 8.2.
- [ ] Clean install on Moodle 5.1 / PHP 8.2.
- [ ] Clean install on Moodle 5.2 / PHP 8.3.
- [ ] PHP 8.4 jobs green on all supported Moodle branches.
- [ ] PostgreSQL compatibility jobs green.
- [ ] MySQL compatibility jobs green.
- [ ] PHP lint green.
- [ ] Moodle CodeSniffer green with zero warnings.
- [ ] Plugin validation green.
- [ ] Upgrade savepoints green.
- [ ] PHPDoc checker green with zero warnings.
- [ ] Mustache lint green.
- [ ] Moodle Grunt/AMD build green.
- [ ] PHPUnit green with `--fail-on-warning`.
- [ ] Privacy user-list/discovery/deletion regression tests green.
- [ ] Release package validation green: one `coursecompliance/` root and no `.git`/`.github` content.

## Functional smoke test

- [ ] Create and run a selective audit.
- [ ] Run a batched audit of at least 100 representative courses before 1.0.
- [ ] Verify parent scan aggregation after all worker tasks finish.
- [ ] Verify a failed worker does not corrupt completed worker results.
- [ ] Verify automatic worker retry and manual retry of failed batches.
- [ ] Record worker time, DB queries and peak memory for the 50/100/250/500-course ladder.
- [ ] Verify Question Bank audit on multichoice, match, shortanswer, numerical, true/false and random pools.
- [ ] Verify Action Center removes stale issues after a newer clean scan.
- [ ] Preview, apply and rollback one Safe AutoFix batch.
- [ ] Verify course indicator and course issue page.
- [ ] Verify session planning and activity operations.
- [ ] Verify Moodle Messaging on audit completion/failure.
- [ ] Verify Settings → Diagnostics on each representative environment.

## Upgrade and privacy

- [ ] Upgrade from the latest 0.18 beta while preserving standards, scans and operation history.
- [ ] Run a new audit after upgrade.
- [ ] Verify Moodle Privacy API export for an administrator with CourseCompliance data.
- [ ] Verify single-user deletion.
- [ ] Verify approved multi-user deletion does not remove another administrator's data.
- [ ] Review `docs/PRIVACY.md` against the current database schema and message usage.

## Manual UI and accessibility

- [ ] Test Boost on desktop and narrow/mobile widths.
- [ ] Verify keyboard navigation and visible focus.
- [ ] Verify navigation regions have labels and current-page state.
- [ ] Verify forms retain Moodle labels/help text.
- [ ] Verify tables remain readable with horizontal overflow where needed.
- [ ] Confirm CourseCompliance CSS does not affect unrelated Moodle pages.

## Publication

- [ ] Update `CHANGELOG.md` and release string.
- [ ] Review `README.md` and `docs/MARKETPLACE.md`.
- [ ] Capture current screenshots using non-production/sample data.
- [ ] Confirm a public source repository, issue tracker and documentation URL exist.
- [ ] Review privacy metadata, export/delete behaviour and Messaging subsystem declaration.
- [ ] Review capabilities and `sesskey` protection.
- [ ] Build ZIP with the single top-level directory `coursecompliance/`.
- [ ] Install the exact release ZIP on a clean supported site before publishing.
- [ ] Ensure the Git tag matches `$plugin->release`.
