# Upgrading Course Compliance Center

## Upgrade from 1.0.0 to 1.1.0-rc1

Use a staging copy first. Back up plugin data and course settings affected by pending activity operations.
Finish existing CourseCompliance jobs before replacing the code, then pause cron during the Moodle upgrade.
Install the ZIP as an update to tool_coursecompliance; do not uninstall the old plugin.
The normal Moodle upgrade adds four scan fields (configsnapshot, confighash, finaldurationms, finaldbqueries).
No manual SQL is required. Existing audit rows remain historical and are not assigned invented snapshots.
Resume cron only after the database upgrade completes. Run a small audit, preview/apply/rollback and the
regression suite before increasing scope. A code-only downgrade after schema upgrade is not a supported
rollback; restore the matching code/data backup if rollback is necessary.

This build is an RC. See VALIDATION-1.1.md for tests not executed in the authoring environment.


## General procedure

1. Back up the Moodle database and the existing `tool_coursecompliance` code directory.
2. Replace the plugin code with the new release using the correct administration-tool path for your Moodle branch.
3. Run the normal Moodle upgrade (`admin/cli/upgrade.php` or the web upgrade screen).
4. Purge Moodle caches after the upgrade.
5. Confirm that cron/AdHoc task processing is running.
6. Run one small selective audit before launching a large category audit.

## 0.18 to 0.19

0.19.0-rc1 adds release-readiness documentation, a read-only Diagnostics page and more complete Privacy API support. It does not change the CourseCompliance database schema. Existing standards, scans, question diagnostics, exceptions and operation history are retained. After upgrading, open **CourseCompliance → Settings → Diagnostics** and confirm that the detected Moodle, PHP and database environment is inside the supported range.

## 0.17 to 0.18

0.18 adds scan reliability/performance fields (`durationms`, `dbqueries`, `peakmemory`, `retrycount`) to the scan table. Existing audit data is retained. After the Moodle upgrade completes, open **CourseCompliance → Settings** and review the worker batch size and automatic retry count before the next large audit.

## 0.16 to 0.17

0.17 changes compatibility metadata, CI coverage and release documentation. It does not add or remove database fields. Existing scans, Action Center state, standards, exceptions, Question Bank diagnostics and activity-operation history are retained.

## Moodle core upgrades

When upgrading the Moodle site between 5.0, 5.1 and 5.2, keep CourseCompliance updated to the newest compatible release first. After the Moodle core upgrade, run a small audit and verify Question Bank, Safe AutoFix and background-task processing before resuming large batch operations.
