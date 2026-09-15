# Upgrading Course Compliance Center

## General procedure

1. Back up the Moodle database and the existing `tool_coursecompliance` code directory.
2. Replace the plugin code with the new release using the correct administration-tool path for your Moodle branch.
3. Run the normal Moodle upgrade (`admin/cli/upgrade.php` or the web upgrade screen).
4. Purge Moodle caches after the upgrade.
5. Confirm that cron/AdHoc task processing is running.
6. Run one small selective audit before launching a large category audit.

## 0.16 to 0.17

0.17 changes compatibility metadata, CI coverage and release documentation. It does not add or remove database fields. Existing scans, Action Center state, standards, exceptions, Question Bank diagnostics and activity-operation history are retained.

## Moodle core upgrades

When upgrading the Moodle site between 5.0, 5.1 and 5.2, keep CourseCompliance updated to the newest compatible release first. After the Moodle core upgrade, run a small audit and verify Question Bank, Safe AutoFix and background-task processing before resuming large batch operations.
