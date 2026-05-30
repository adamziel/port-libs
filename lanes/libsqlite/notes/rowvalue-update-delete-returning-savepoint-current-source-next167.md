# Row-value UPDATE/DELETE RETURNING savepoint current-source next167

Status: focused PHP behavior growth for row-value `UPDATE` / `DELETE ... RETURNING` clause parsing inside savepoint retry execution.

This slice makes `SQLiteUpdateDeleteReturningSql` detect `WHERE`, `RETURNING`, `ORDER BY`, and `LIMIT` only at top-level SQL clause boundaries. String literals inside row-value assignments, row-value predicates, and `RETURNING` expressions can now contain clause-looking text such as `' WHERE literal'`, `' RETURNING literal'`, `' ORDER BY literal'`, and `' LIMIT literal'` without truncating the parsed statement.

Application smoke: `application-rowvalue-update-delete-returning-savepoint-current-source-next167.php` models a copied `wp_options` import batch where draft option values contain clause-looking text, the attempted `RETURNING` rows are discarded by `ROLLBACK TO`, and retry UPDATE/DELETE statements yield rows from the restored current source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext167Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next167.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext167Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext158Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext161Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext167Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningConflictCurrentSourceNext130Test.php lanes/libsqlite/tests/SQLiteRowValueConflictReturningSavepointCurrentSourceNext138Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next167.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test delta: +60 focused PASS lines in `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext167Test.php`.

Expected dashboard delta: `phpPass` moves from `75459` to `75519`. Mapped upstream coverage remains `611 / 1589`; this is focused PHP executor/parser behavior over existing row-value DML/savepoint inventory rather than a new mapped upstream unit.

Non-overlap: this avoids accepted row-value conflict/savepoint clusters next130, next133, next138, next144, next156, next158, and next161; trigger RETURNING/savepoint clusters; SELECT SQL text/subquery/group/order clusters; VFS/WAL/pager savepoint application clusters; B-tree, JSON, PRAGMA, planner, and encoding surfaces. The new behavior is specifically top-level clause-boundary detection when row-value UPDATE/DELETE RETURNING statements carry clause-looking string literals through current-source rollback and retry.

Dependency closure: no new support component is needed. The slice reuses the lane-local UPDATE/DELETE RETURNING executor, row-value predicate/projection support, and existing savepoint rollback/retry planner.

Next task: continue with broader SQL executor/planner correctness or another non-overlapping row-value DML current-source edge; avoid another savepoint wrapper unless it applies a distinct parser/executor behavior.
