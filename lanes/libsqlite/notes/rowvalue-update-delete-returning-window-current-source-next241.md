# rowvalue-update-delete-returning-window-current-source-next241

Status: focused PHP behavior growth for current-source row-value
`UPDATE`/`DELETE ... RETURNING` window execution.

This slice adds a bounded `CURRENT ROW` frame fence over the accepted next238
current/next source pair stream. Each replayed, restart-only, or discarded
RETURNING pair is isolated as `ROWS BETWEEN CURRENT ROW AND CURRENT ROW`, so a
retry-yielded row cannot inherit adjacent discarded current-source rows after a
savepoint rollback.

Application smoke:

- `lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next241.php`
  models copied `wp_options` rows where plugin/theme retry updates and transient
  cleanup deletes are selected by row-value subqueries.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Test.php
php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next241.php
No syntax errors detected in all changed PHP files.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 72 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next241.php --self-test
application-rowvalue-returning-window-current-source-next241 self-test passed
```

Expected dashboard movement: `phpPass +72` from the new focused test file.
`benchmarkDenominator.mapped` remains `644 / 1589`; this is current-source PHP
behavior over already mapped row-value, UPDATE/DELETE RETURNING, savepoint, and
window inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next237 `EXCLUDE CURRENT ROW` retry windows,
next238 current/next pair classification, next235 window materialization,
row-value savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table,
B-tree, PRAGMA, planner, encoding, and suite-evidence clusters. The narrower
surface is `CURRENT ROW` frame isolation over already classified current/next
RETURNING pairs.

Dependency closure: no new support component is needed; this reuses native
row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next235
RETURNING-window rows, and next238 current/next pair classification.

Next task: move to a non-overlapping SQL executor/planner, WAL/VFS durability,
JSON table planner, encoding/collation, or B-tree closure gap; avoid another
row-value window variant unless it removes a concrete current-source blocker.
