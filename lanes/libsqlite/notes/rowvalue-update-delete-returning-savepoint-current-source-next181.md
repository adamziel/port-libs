# rowvalue-update-delete-returning-savepoint-current-source-next181

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING execution.

This slice fixes row-value `IN` / `NOT IN` membership over nullable tuples.
The bounded executor already handled nullable row-value `=` / `<>` when a
later non-NULL element proves inequality; `IN` still used lexicographic
comparison and returned UNKNOWN too early. The new behavior uses row-value
equality semantics for each RHS tuple so Application cleanup predicates such as
`(blog_id, status, option_name) NOT IN (...)` keep exact nullable tuple
members and select rows that are deterministically outside the list.

Application smoke:
`application-rowvalue-in-savepoint-current-source-next181.php` models a copied
`wp_options` import savepoint with nullable row-value `NOT IN`, speculative
RETURNING rows, an `UPDATE OR FAIL` conflict, rollback-to-savepoint, and retry
from the restored current source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext181Test.php
php -l lanes/libsqlite/examples/application-rowvalue-in-savepoint-current-source-next181.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext181Test.php
php lanes/libsqlite/examples/application-rowvalue-in-savepoint-current-source-next181.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Dashboard delta: expected `phpPass +50` from the new focused test file
(`85432 -> 85482`). Mapped upstream coverage remains conservative because this
is focused PHP executor behavior over already mapped row-value/update-delete
RETURNING primitives.

Non-overlap: avoids accepted next173 FAIL stream rollback, next176 nullable
row-value equality/inequality, next178 inner rollback RETURNING behavior,
trigger RETURNING, WAL/savepoint, B-tree, JSON table, PRAGMA, encoding, and
planner clusters. The new surface is specifically nullable row-value `IN` /
`NOT IN` membership in WHERE and RETURNING expressions through UPDATE/DELETE
RETURNING savepoint retry.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing savepoint current-source
retry plan.

Next task: continue with a non-overlapping SQL executor/planner gap or pivot
to the next higher-yield WAL/B-tree/JSON closure target.
