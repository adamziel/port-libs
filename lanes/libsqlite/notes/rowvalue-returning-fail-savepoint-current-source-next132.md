# Row-Value RETURNING OR FAIL Savepoint Current Source Next132

This slice adds a bounded current-source model for SQLite `UPDATE OR FAIL ... RETURNING` inside a savepoint when row-value assignments hit a unique conflict. The existing executor still throws for ordinary `OR FAIL` callers, but the new preserve mode lets `SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan` record SQLite's statement behavior: earlier row changes and RETURNING rows survive, the failing row is restored, later rows are not attempted, and the savepoint remains open for caller recovery.

Focused verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReturningFailSavepointCurrentSourceNext132Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 52 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-rowvalue-returning-fail-savepoint-current-source-next132.php
# emits a JSON summary for a copied wp_options import savepoint where row 8 yields RETURNING before row 7 hits a multisite unique-key conflict.
```

Dashboard delta: `phpPass` moves from `55029` to `55081` from 52 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused PHP behavior over already mapped row-value DML/conflict/savepoint primitives rather than a new manifest-backed upstream inventory unit.

Non-overlap: avoids accepted next126/128/130 row-value savepoint and OR IGNORE/REPLACE/ROLLBACK conflict coverage, accepted DML trigger RETURNING conflict handling, savepoint rollback image/WAL/VFS slices, schema/WAL/B-tree/JSON/SELECT surfaces, and the accepted batch130 row-value UPDATE/DELETE RETURNING conflict subset. The new behavior is specifically `OR FAIL` partial statement preservation under a savepoint.

Dependency closure: no new support component is needed. The slice reuses native PHP row-value UPDATE RETURNING conflict handling and savepoint current-source modeling.
