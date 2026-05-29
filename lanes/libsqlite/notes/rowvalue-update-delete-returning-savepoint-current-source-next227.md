# rowvalue-update-delete-returning-savepoint-current-source-next227

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
tuple subqueries.

This slice teaches `SQLiteUpdateDeleteReturningSql` to parse
`SELECT DISTINCT` row-value tuple sources and de-duplicate tuples before
applying LIMIT/OFFSET. The new
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan` proves
that attempted UPDATE/DELETE RETURNING rows sourced from duplicated
`wp_optionmeta` tuples are rolled back to the savepoint image, and retry
statements re-read the de-duplicated current source.

WordPress smoke:
`wordpress-rowvalue-distinct-tuple-savepoint-next227.php` models a copied
`wp_options` import where duplicate optionmeta rows drive a distinct tuple
batch for option updates, transient deletion, rollback, and retry.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext227Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 67 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-rowvalue-distinct-tuple-savepoint-next227.php --self-test
```

Expected dashboard delta: `phpPass` moves from `110487` to `110554` from 67
newly passing focused assertions. Mapped upstream coverage remains
`628 / 1589`; this is focused current-source behavior over already mapped
row-value UPDATE/DELETE RETURNING inventory.

Non-overlap: avoids accepted next219 `LIMIT -1 OFFSET` tuple-source semantics,
next224 nested savepoint release rollback, next220/217/208 conflict-action
savepoint behavior, trigger RETURNING, WAL/VFS, JSON table, planner, and
B-tree clusters. The new surface is specifically `SELECT DISTINCT` tuple
sources inside row-value UPDATE/DELETE RETURNING savepoint rollback and retry.

Dependency closure: no new support component is needed. The slice reuses the
native PHP UPDATE/DELETE RETURNING executor and row-array savepoint modeling,
adding only bounded DISTINCT tuple-source parsing/de-duplication.

Root harness status: not run - isolated micro-slice.
