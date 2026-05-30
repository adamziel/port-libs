# rowvalue-update-delete-returning-savepoint-current-source-next215

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
subquery tuple lists with ORDER BY/LIMIT inside a savepoint current source.

This slice extends `SQLiteUpdateDeleteReturningSql` so row-value
`IN (SELECT ...)` and `NOT IN (SELECT ...)` tuple subqueries can use bounded
`ORDER BY`, `LIMIT`, and `OFFSET` clauses before feeding UPDATE/DELETE
RETURNING row selection. The next215 plan proves the attempted ordered/limited
UPDATE and DELETE streams are discarded by `ROLLBACK TO`, then retry statements
read the restored savepoint image and release the current source.

Application smoke:
`application-rowvalue-subquery-limit-savepoint-retry.php`
models copied `wp_options` cleanup driven by ordered migration metadata. It
keeps only the current-source retry rows after rollback and deletes the network
drop row selected by the ordered metadata subquery.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueSubqueryLimitSavepointRetryTest.php
php -l lanes/libsqlite/examples/application-rowvalue-subquery-limit-savepoint-retry.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSubqueryLimitSavepointRetryTest.php
1 test files, 52 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-subquery-limit-savepoint-retry.php --self-test
application-rowvalue-subquery-limit-savepoint-retry self-test passed
```

Expected dashboard movement: `phpPass` +52 focused PASS lines
(`103870 -> 103922`) if accepted. Mapped upstream coverage is unchanged at
`623 / 1589`; this is current-source PHP executor coverage over already mapped
row-value UPDATE/DELETE RETURNING inventory rather than a newly hydrated
upstream Tcl row.

Non-overlap: avoids accepted next181 nullable row-value membership, next184
VALUES tuple lists, next191 assignment predicates, next193/next209 OR FAIL
conflict streams, next200 ABORT rollback, next203 IGNORE/REPLACE, next205
release handoff, next212 bare SELECT subquery tuple lists, trigger RETURNING,
WAL/pager/VFS savepoint application, JSON table, planner, encoding, and B-tree
clusters. The new behavior is specifically ordered and limited row-value
subquery tuple lists inside UPDATE/DELETE RETURNING savepoint retry.

Dependency closure: no new support component is needed. The slice reuses the
native PHP UPDATE/DELETE RETURNING executor, `SQLiteSelectResult` ordering and
limit helpers, and existing savepoint current-source modeling.

Next task: continue broader executor/planner or storage application work; avoid
another row-value savepoint slice unless it covers a distinct upstream
subquery/current-source behavior.
