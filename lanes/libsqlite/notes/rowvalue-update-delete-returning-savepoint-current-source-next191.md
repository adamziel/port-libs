# rowvalue-update-delete-returning-savepoint-current-source-next191

Status: focused PHP behavior growth for row-value predicates used as UPDATE
assignment expressions inside UPDATE/DELETE RETURNING savepoint retry.

This slice extends `SQLiteUpdateDeleteReturningSql` so row-value predicate
expressions in `SET` assignments evaluate to SQLite-style `1`, `0`, or `NULL`
instead of being stored as literal SQL text. Covered assignment predicates are
row-value `IN` / `NOT IN`, `BETWEEN` / `NOT BETWEEN`, `IS` / `IS NOT`, `IS
DISTINCT FROM` / `IS NOT DISTINCT FROM`, and comparison operators. The focused
coverage runs those flags through speculative UPDATE RETURNING rows, a DELETE
RETURNING cleanup, `ROLLBACK TO` suppression, and retry from the restored
current source.

Application smoke:
`application-rowvalue-assignment-savepoint-current-source-next191.php --self-test`
models copied `wp_options` cleanup where row-value predicates populate import
flag columns, attempted RETURNING rows are discarded by rollback, and retry
starts from the savepoint image.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext191Test.php
php -l lanes/libsqlite/examples/application-rowvalue-assignment-savepoint-current-source-next191.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext191Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-assignment-savepoint-current-source-next191.php --self-test
application-rowvalue-assignment-savepoint-current-source-next191 self-test passed
```

Expected dashboard movement: `phpPass +53` from the new focused test file,
`91519 -> 91572`. Mapped upstream coverage is unchanged; this is focused PHP
executor behavior over already mapped row-value DML/savepoint/RETURNING
inventory.

Dependency closure: no new support component is needed. The slice reuses the
native PHP UPDATE/DELETE RETURNING executor and the existing bounded
savepoint current-source retry model.

Non-overlap: avoids accepted next176 nullable equality, next181 nullable
`IN`/`NOT IN`, next184 `IN (VALUES ...)`, next188 empty tuple lists, next185
/ next187 conflict-action savepoint rollback, trigger RETURNING, WAL/pager/VFS,
B-tree, JSON, encoding, planner, and suite-runner clusters. The new surface is
specifically row-value predicates in UPDATE assignment expressions, where the
old evaluator could persist literal predicate SQL into the updated row image.

Next task: continue with a different SQL executor/planner gap or a storage
durability edge; avoid another row-value savepoint variant unless it reaches a
new parser/executor behavior.
