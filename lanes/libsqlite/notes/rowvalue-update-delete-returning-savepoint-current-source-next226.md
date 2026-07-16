# Row-value UPDATE/DELETE RETURNING savepoint current-source next226

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
`SELECT DISTINCT` tuple sources.

This slice teaches bounded row-value `IN (SELECT ...)` tuple sources to accept
`SELECT DISTINCT` and de-duplicate projected tuple values before LIMIT/OFFSET
is applied. The new
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext226Plan` covers
UPDATE and DELETE RETURNING statements whose duplicate optionmeta tuple sources
are rolled back to a savepoint image and retried from the restored current
source.

Application path:
`application-rowvalue-distinct-subquery-current-source-next226.php` models a
copied `wp_options` import where duplicate `wp_optionmeta` migration rows must
drive each UPDATE/DELETE RETURNING target only once.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext226Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext226Test.php
php -l lanes/libsqlite/examples/application-rowvalue-distinct-subquery-current-source-next226.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext226Test.php
php lanes/libsqlite/examples/application-rowvalue-distinct-subquery-current-source-next226.php
```

Focused result: `1 test files, 62 assertions, 0 failures`.

Dashboard delta: `phpPass` moves from `108262` to `108324` by the focused
PASS-line/assertion delta. Mapped upstream coverage remains unchanged; this is
additional current-source PHP behavior over already mapped row-value
UPDATE/DELETE RETURNING inventory.

Non-overlap: avoids accepted next219 negative LIMIT/OFFSET tuple sources,
next213 positive ORDER/LIMIT row-value subquery sources, next217 OR ROLLBACK
transaction rollback, next212 plain row-value subqueries, savepoint page-image
rollback, WAL/VFS/pager durability, trigger/RETURNING, JSON table, planner,
B-tree, PRAGMA, and encoding clusters. The new surface is specifically
`SELECT DISTINCT` tuple-source de-duplication inside row-value UPDATE/DELETE
RETURNING savepoint rollback and retry.

Dependency closure: no new support component is needed. The slice reuses the
lane-local row-array UPDATE/DELETE RETURNING executor and extends its bounded
row-value SELECT tuple handling.

Next task: continue with a non-overlapping SQL executor/planner or storage
behavior gap; avoid another row-value savepoint variant unless it removes a
fresh current-source blocker.
