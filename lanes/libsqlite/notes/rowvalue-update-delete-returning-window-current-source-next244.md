# rowvalue-update-delete-returning-window-current-source-next244

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE ...
RETURNING` current-source window execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan`
on top of the accepted current/next pair and `CURRENT ROW` frame layers. It
computes lag/lead transition windows over each action partition after a
savepoint rollback and retry, so copied Application import/migration batches can
tell whether a yielded RETURNING row is adjacent to discarded current-source
rows or restart-only retry rows.

Application smoke:

- `lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next244.php --self-test`
  models copied `wp_options` retry updates and transient cleanup deletes
  selected by row-value subqueries.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Test.php
php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next244.php
No syntax errors detected in all changed PHP files.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 79 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next244.php --self-test
application-rowvalue-returning-window-current-source-next244 self-test passed
```

Expected dashboard movement: `phpPass +79` from the new focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
behavior over already mapped row-value, UPDATE/DELETE RETURNING, savepoint, and
window inventory.

Non-overlap: avoids next238 pair classification, next239 statement partitions,
next240 peer exclusions, next241 `CURRENT ROW` frames, row-value UPSERT,
trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and
suite-evidence clusters. The narrower behavior is lag/lead transition-chain
windowing across already isolated current/next RETURNING pairs.

Dependency closure: no new support component is needed; this reuses native
row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next238 pair
classification, and next241 `CURRENT ROW` frame isolation.

Next task: move to a non-overlapping SQL executor/planner, WAL/VFS durability,
JSON table planner, encoding/collation, or B-tree closure gap; avoid another
row-value window variant unless it removes a concrete current-source blocker.
