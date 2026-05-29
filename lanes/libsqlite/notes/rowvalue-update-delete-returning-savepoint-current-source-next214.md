# rowvalue-update-delete-returning-savepoint-current-source-next214

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source savepoint handling.

This slice adds bounded row-value `IN (SELECT ...)` subquery support for
`ORDER BY` plus `LIMIT`/`OFFSET` tuple streams in `SQLiteUpdateDeleteReturningSql`
and covers a WordPress-style copied `wp_options` savepoint flow where:

- an attempt phase updates/deletes rows selected by ordered metadata subqueries;
- `ROLLBACK TO` restores the savepoint image and suppresses the attempt
  RETURNING stream;
- retry UPDATE/DELETE RETURNING statements read the restored current source
  while honoring ordered subquery windows;
- limited subqueries can exclude a NULL metadata tuple, avoiding stale
  `NOT IN` poisoning during cleanup.

WordPress smoke:
`wordpress-rowvalue-ordered-subquery-savepoint-current-source-next214.php`
models plugin/import metadata selecting only the highest-priority copied
options rows for retry and network cleanup.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext214Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext214Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-ordered-subquery-savepoint-current-source-next214.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext214Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-ordered-subquery-savepoint-current-source-next214.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `103870` to `103935` from 65 newly
passing focused PASS lines. Mapped upstream coverage remains `623 / 1589`.

Non-overlap: avoids accepted rowvalue next192/200/207/212 conflict, failure,
subquery, and savepoint-current-source variants. The new behavior is ordered
and limited row-value SELECT tuple streams inside UPDATE/DELETE RETURNING
savepoint rollback/retry, not another ABORT/FAIL/ROLLBACK conflict variant.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor, row-value predicates, and existing
row-array savepoint modeling.

Next task: continue with a non-overlapping SQL executor/planner, WAL/pager, or
B-tree closure gap; avoid another rowvalue savepoint variant unless it removes
a named upstream runner blocker or adds materially distinct assertion growth.
