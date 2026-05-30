# rowvalue-update-delete-returning-savepoint-current-source-next197

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source handling around explicit `ROLLBACK TO` of an inner savepoint.

This slice adds
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`. It
models a copied `wp_options` import where outer row-value UPDATE RETURNING work
is preserved, inner DELETE/UPDATE RETURNING work is executed and then explicitly
rolled back to the still-open inner savepoint, and retry UPDATE/DELETE
RETURNING statements read from the restored current source before releasing the
inner and outer savepoints.

Application smoke:
`application-rowvalue-rollback-to-current-source-next197.php` covers transient
cleanup and option rewrite batches where an inner batch is rolled back, its
RETURNING rows are suppressed from durable output, and the retry keeps the
outer rewrite while restoring deleted transient rows and pre-inner option
values.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext197Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 80 assertions, 0 failures
```

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext197Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext197Test.php

php -l lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next197.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next197.php
```

```text
php lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next197.php --self-test
application-rowvalue-rollback-to-current-source-next197 self-test passed
```

Dashboard delta: `phpPass` moves from `95013` to `95093` from 80 newly passing
focused PASS lines. Mapped upstream coverage remains `618 / 1589`; this is
fresh focused PHP behavior over already mapped row-value, RETURNING, and
savepoint primitives rather than a new upstream inventory row.

Non-overlap: avoids accepted rowvalue next156/158/161/172/178/180/189
rollback/retry variants, next192 `UPDATE OR ABORT` statement rollback, next193
`UPDATE OR FAIL` partial stream rollback, rowvalue186 conflict queue,
UPDATE/DELETE RETURNING LIMIT/OFFSET, and pager/WAL savepoint byte/page-image
surfaces. The new behavior is explicit `ROLLBACK TO` after successful inner
row-value DELETE/UPDATE RETURNING statements, preserving the outer current
source while restoring the inner savepoint image for retry statements.

Dependency closure: no new support component is needed. The slice reuses the
lane-local PHP UPDATE/DELETE RETURNING executor, row-value assignment/predicate
support, and bounded row-array savepoint modeling.

Next task: move to a distinct SQL executor/planner, pager/VFS durability, JSON
planner, encoding/collation, or B-tree closure gap; avoid another row-value
savepoint variant unless it removes a named accepted-head blocker.
