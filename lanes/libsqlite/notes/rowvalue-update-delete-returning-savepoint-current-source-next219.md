# Row-value UPDATE/DELETE RETURNING savepoint current-source next219

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
subquery sources.

This slice fixes row-value `IN (SELECT ...)` tuple sources so `LIMIT -1
OFFSET n` follows SQLite semantics: negative LIMIT means no upper bound after
the offset. The new
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext219Plan` covers
UPDATE and DELETE RETURNING statements whose row-value subquery source is
rolled back to a savepoint image and then retried from the restored current
source.

Application path:
`application-rowvalue-negative-limit-offset-current-source-next219.php` models a
copied `wp_options` import where `wp_optionmeta` priority rows drive
row-value UPDATE/DELETE RETURNING batches with `LIMIT -1 OFFSET`.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext219Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext219Test.php
php -l lanes/libsqlite/examples/application-rowvalue-negative-limit-offset-current-source-next219.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext219Test.php
php lanes/libsqlite/examples/application-rowvalue-negative-limit-offset-current-source-next219.php
```

Focused result: `1 test files, 64 assertions, 0 failures`.

Dashboard delta: `phpPass` moves from `106763` to `106827` by the focused
PASS-line/assertion delta. Mapped upstream coverage remains unchanged; this is
additional current-source PHP behavior over already mapped row-value
UPDATE/DELETE RETURNING inventory.

Non-overlap: avoids accepted next213 positive ORDER/LIMIT row-value subquery
sources, next217 OR ROLLBACK transaction rollback, next212 plain row-value
subqueries, savepoint page-image rollback, WAL/VFS/pager durability,
trigger/RETURNING, JSON table, planner, B-tree, PRAGMA, and encoding clusters.
The new surface is specifically `LIMIT -1 OFFSET` tuple-source semantics inside
row-value UPDATE/DELETE RETURNING savepoint rollback and retry.

Dependency closure: no new support component is needed. The slice reuses the
lane-local row-array UPDATE/DELETE RETURNING executor and corrects its bounded
row-value SELECT tuple limit handling.

Next task: continue with a non-overlapping SQL executor/planner or storage
behavior gap; avoid another row-value savepoint variant unless it removes a
fresh current-source blocker.
