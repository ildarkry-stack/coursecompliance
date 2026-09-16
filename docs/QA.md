# Release-candidate QA plan

This document describes the final manual checks recommended before publishing CourseCompliance 1.0.

## Environment coverage

Run the automated GitHub Actions workflow and require all jobs to pass for Moodle 5.0, 5.1 and 5.2. The compatibility matrix covers supported PHP versions and both PostgreSQL and MySQL.

On at least one representative site, enable Moodle developer debugging and confirm that installation, upgrade, navigation and normal plugin use do not produce notices, warnings or deprecated API messages attributable to CourseCompliance.

## Installation and upgrade

Test both of these paths:

1. clean installation from the release ZIP;
2. upgrade from the latest 0.18 beta with existing standards, scans, question diagnostics and activity-operation history.

After upgrade, verify that existing reports remain readable and that a new audit can be queued and completed.

## Functional smoke test

Use representative courses containing Exam, Resit 1 and Resit 2 targets, access restrictions, completion rules, gradebook items and a Question Bank with core question types.

Confirm the main workflow:

- run a selective audit;
- inspect the detailed report and Action Center;
- verify readiness and course indicators;
- inspect Question Bank diagnostics;
- preview and apply one deterministic Safe AutoFix;
- confirm the targeted recheck updates current state;
- preview, apply and safely roll back one Activity Operation;
- run Session Planning against Quiz and Assignment targets;
- verify Moodle Messaging on scan completion;
- open Settings → Diagnostics and confirm the environment checks match the site.

## Reliability and load test

Use the load-test ladder in `PERFORMANCE.md`. Before 1.0, complete at least a 100-course representative scan with Question Bank checks enabled. Record worker duration, database-query counts, peak memory, retries and any failed/stalled workers.

A failed worker must be retryable without deleting completed worker results. A failed final aggregation must be retryable without rerunning successful course scans.

## Privacy and deletion

Run the Privacy API regression suite. Manually confirm that the Moodle data-privacy tooling can discover and export an administrator who has started an audit or created an operation/exception. Bulk deletion must remove only the approved users' CourseCompliance-owned records and preserve other administrators' records.

## Accessibility and UI

Check the plugin with the Boost theme at desktop and narrow/mobile widths. Verify keyboard navigation, visible focus, table readability, labelled navigation regions, form labels, notification semantics and sufficient contrast. CourseCompliance CSS must remain scoped to the plugin pages.

## Release package

The distributable ZIP must contain one top-level directory named `coursecompliance/`, include `version.php`, `README.md`, `LICENSE` and `db/install.xml`, and exclude `.git/` and `.github/` metadata.
