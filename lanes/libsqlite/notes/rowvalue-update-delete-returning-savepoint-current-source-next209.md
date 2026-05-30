# rowvalue-update-delete-returning-savepoint-current-source-next209

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source handling around `UPDATE OR FAIL`.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Plan`
and a narrow DML executor extension for simple `CASE ... WHEN ... THEN ...
ELSE ... END` assignment expressions. It models SQLite `OR FAIL` behavior for
copied `wp_options` repair rows:

- prior row-value UPDATE/DELETE RETURNING streams inside the savepoint remain
  yielded and current;
- a single `UPDATE OR FAIL` can preserve earlier successful row mutations and
  RETURNING rows before a later row hits the `(blog_id, option_name)` unique
  constraint;
- the conflicting row is restored to the statement-start image and its
  attempted RETURNING row is suppressed;
- retry UPDATE/DELETE RETURNING statements read from that preserved
  current-source image.

Application smoke:
`lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-current-source-next209.php`
models copied `wp_options` repair and transient cleanup through the same
current-source path.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php

php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Plan.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Test.php

php -l lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-current-source-next209.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-current-source-next209.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 75 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-current-source-next209.php --self-test
application-rowvalue-or-fail-savepoint-current-source-next209 self-test passed
```

Focused test delta: +75 focused PHP PASS lines/assertions. Expected
`lane-status.json` `phpPass` moves from `100087` to `100162`; mapped upstream
coverage remains `621 / 1589`.

Non-overlap: avoids accepted next133 row-value `IS` / `IS NOT`, next176 NULL
equality/inequality, next192 `OR ABORT`, next206 released-inner outer rollback,
row-value UPSERT, DELETE-only rollback, trigger RETURNING, WAL/pager/VFS,
B-tree, JSON, PRAGMA, encoding, and suite-runner clusters. The new surface is
specifically row-value `UPDATE OR FAIL ... RETURNING` current-source behavior
with prior-row preservation and conflicting-row RETURNING suppression.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP UPDATE/DELETE RETURNING, row-value predicate handling,
unique conflict handling, and savepoint current-source orchestration.

Next task: continue with a non-overlapping row-value/planner executor gap or a
larger SQL executor current-source behavior; avoid repeating `OR FAIL`,
`OR ABORT`, released-inner rollback, or nullable row-value comparison surfaces.
