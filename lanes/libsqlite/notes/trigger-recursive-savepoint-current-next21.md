# Recursive Trigger Savepoint Current Next21

This slice adds `SQLiteRecursiveTriggerSavepointPlan`, a bounded native PHP wrapper for recursive `AFTER INSERT` trigger effects that hit a current-row conflict inside a named savepoint.

The behavior is intentionally scoped to copied Application `wp_options` import rows:

- successful recursive trigger expansion leaves the current savepoint rows plus descendants;
- recursive `ON CONFLICT ROLLBACK` restores the current savepoint image instead of leaking the seed/child rows;
- `FAIL`, `IGNORE`, and `REPLACE` remain observable without savepoint rollback;
- `recursive_triggers` and `max_depth` options are preserved from the underlying trigger executor.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveSavepointCurrentNext21Test.php` -> `1 test files, 44 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-recursive-trigger-savepoint-current.php` -> reports `rollbackScope: savepoint`, current option names `siteurl, preflight_marker`, and discarded option name `plugin_seed`
- `php -l lanes/libsqlite/src/SQLiteRecursiveTriggerSavepointPlan.php`, `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveSavepointCurrentNext21Test.php`, and `php -l lanes/libsqlite/examples/application-recursive-trigger-savepoint-current.php` pass
- `git diff --check -- lanes/libsqlite` passes

Dependency closure: no new support component is needed. The slice reuses the accepted recursive trigger conflict executor and models only the savepoint-current wrapper state needed for rollback diagnostics.

Non-overlap: avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, JSON table source/cursor/constraint work, SQL subquery/comma-LIMIT/group/order text dispatch, Unicode GLOB, and B-tree overflow/root-collapse/page-move clusters.
