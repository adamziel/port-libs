# rowvalue-update-delete-returning-savepoint-current-source-next198

Status: focused PHP behavior growth for current-source UPDATE/DELETE
RETURNING savepoint execution.

This slice adds SQLite-style scalar `BETWEEN` and `NOT BETWEEN` support to the
bounded `SQLiteUpdateDeleteReturningSql` executor. It covers DML WHERE
selection, RETURNING expressions, and UPDATE assignment expressions, including
inclusive bounds, expression bounds, SQL NULL unknown handling, and rollback-to
savepoint suppression before retry.

WordPress smoke:
`wordpress-rowvalue-between-savepoint-current-source-next198.php` models a
copied `wp_options` import that marks current byte-range rows, deletes only
out-of-range transient rows, rolls back attempted RETURNING rows, and retries
from the original current source while keeping NULL-sized rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext198Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-between-savepoint-current-source-next198.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext198Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-between-savepoint-current-source-next198.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `96383` to `96434` from 51 newly passing
focused PASS lines. Mapped upstream coverage remains `619 / 1589`; this is
fresh focused PHP executor behavior over already mapped UPDATE/DELETE
RETURNING and row-value savepoint primitives.

Non-overlap: avoids accepted next192 `UPDATE OR ABORT` statement rollback,
next195 unary `NOT` row-value distinct predicates, accepted row-value `IS` /
`IS NOT`, nullable equality/inequality, row-value `IN`, BETWEEN row-value
tuple comparisons, OR ROLLBACK/FAIL/IGNORE/REPLACE conflict handling, and
accepted trigger/WAL/pager/B-tree/JSON surfaces. The new surface is scalar
`BETWEEN` / `NOT BETWEEN` in DML RETURNING executor selection/projection/
assignment under savepoint rollback/retry.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing current-source savepoint
rollback/retry model.

Next task: continue with a non-overlapping SQL executor/planner, WAL/pager, or
B-tree closure gap; avoid another rowvalue savepoint variant unless it removes
a named upstream runner blocker or adds materially distinct assertion growth.
