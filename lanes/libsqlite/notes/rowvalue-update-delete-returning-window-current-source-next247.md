# rowvalue-update-delete-returning-window-current-source-next247

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE ...
RETURNING` current-source window execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan`
on top of the accepted next244 transition-chain window layer. It computes
`GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE GROUP`
receipts for retry-yielded, restart-only, and discarded current-source
RETURNING rows after a savepoint rollback.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next247.php --self-test`
  models copied `wp_options` retry updates and transient cleanup deletes
  selected by row-value subqueries.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next247.php
No syntax errors detected in all changed PHP files.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 72 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next247.php --self-test
wordpress-rowvalue-returning-window-current-source-next247 self-test passed
```

Expected dashboard movement: `phpPass +72` from the new focused test file.
Mapped upstream coverage remains unchanged; this is current-source PHP behavior
over already mapped row-value, UPDATE/DELETE RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted next244 lag/lead transition-chain edges, next243
tuple frames, next241 `CURRENT ROW` frames, next240 peer exclusions, row-value
UPSERT, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner,
encoding, and suite-evidence clusters. The narrower behavior is GROUPS
`EXCLUDE GROUP` accounting over already classified transition partitions.

Dependency closure: no new support component is needed; this reuses native
row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next241
current-row frames, and next244 transition chains.

Next task: move to a non-overlapping SQL executor/planner, WAL/VFS durability,
JSON table planner, encoding/collation, or B-tree closure gap; avoid another
row-value window variant unless it removes a concrete current-source blocker.
