# Trigger Recursive Savepoint Returning Current Next34

- Scope: bounded `AFTER INSERT` recursive trigger savepoint behavior where a trigger-raised rollback restores the current savepoint and clears committed `RETURNING` rows.
- Non-overlap: avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback apply, UPSERT trigger/FK yield, and batch28 recursive DML cycle surfaces. This slice is limited to `SQLiteSavepointTriggerRollbackPlan` direct-statement `RETURNING` diagnostics.
- Application path: copied `wp_options` plugin-import rows can now report direct `RETURNING` rows for successful top-level inserts while excluding recursive audit rows; on trigger rollback, current savepoint rows are restored and only attempted-returning diagnostics remain.
- Dependency closure: no new support component is required. The slice reuses the existing native `SQLiteSavepointStack` and savepoint-trigger planner.
- Verification target: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveSavepointReturningCurrentNext34Test.php` plus the Application smoke under `lanes/libsqlite/examples/application-trigger-savepoint-returning-current-next34.php`.
