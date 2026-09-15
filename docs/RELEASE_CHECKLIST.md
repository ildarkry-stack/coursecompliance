# Release checklist

## Automated gates

- [ ] Clean install on Moodle 5.0 / PHP 8.2.
- [ ] Clean install on Moodle 5.1 / PHP 8.2.
- [ ] Clean install on Moodle 5.2 / PHP 8.3.
- [ ] PHP 8.4 jobs green on all supported Moodle branches.
- [ ] PostgreSQL 16 compatibility jobs green.
- [ ] MySQL 8.4 compatibility jobs green.
- [ ] PHP lint green.
- [ ] Moodle CodeSniffer green with zero warnings.
- [ ] Plugin validation green.
- [ ] Upgrade savepoints green.
- [ ] PHPDoc checker green with zero warnings.
- [ ] Mustache lint green.
- [ ] Grunt/AMD lint green with zero warnings.
- [ ] PHPUnit green with `--fail-on-warning`.

## Functional smoke test

- [ ] Create and run a selective audit.
- [ ] Run a batched audit of at least 50 courses.
- [ ] Verify parent scan aggregation after all worker tasks finish.
- [ ] Verify a failed worker does not corrupt completed worker results.
- [ ] Verify automatic worker retry and manual retry of failed batches.
- [ ] Record worker time, DB queries and peak memory for the 50/100/250/500-course ladder.
- [ ] Verify Question Bank audit on multichoice, match, shortanswer, numerical, true/false and random pools.
- [ ] Verify Action Center removes stale issues after a newer clean scan.
- [ ] Preview, apply and rollback one Safe AutoFix batch.
- [ ] Verify course indicator and course issue page.
- [ ] Verify session planning and activity operations.

## Publication

- [ ] Update `CHANGELOG.md` and release string.
- [ ] Review `README.md` screenshots and feature list.
- [ ] Review privacy metadata and export/delete behaviour.
- [ ] Review capabilities and `sesskey` protection.
- [ ] Build ZIP with the single top-level directory `coursecompliance/`.
- [ ] Install the release ZIP on a clean site before publishing.
