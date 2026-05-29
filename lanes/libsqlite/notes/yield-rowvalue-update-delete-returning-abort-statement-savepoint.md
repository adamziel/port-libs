# rowvalue-update-delete-returning-abort-statement-savepoint

## Behavior

Added the canonical row-value savepoint abort-statement surface for the
current-source distinction between `UPDATE OR ABORT ... RETURNING` and
savepoint rollback. The failed ABORT statement suppresses its own RETURNING
stream and leaves the savepoint open, while earlier savepoint UPDATE/DELETE
changes remain visible to retry UPDATE/DELETE row-value predicates.

This is intentionally disjoint from accepted FAIL-stream rollback behavior and
the earlier inner rollback-to-savepoint behavior. This slice does not model
WAL, pager, B-tree, JSON table, VFS locking, grouped SELECT, or expression
ORDER BY surfaces.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueAbortStatementSavepointTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-rowvalue-abort-statement-savepoint.php --self-test
```

Result:

```text
wordpress-rowvalue-abort-statement-savepoint self-test passed
```

## Dependency Closure

No new support component is needed. The patch reuses `SQLiteUpdateDeleteReturningSql`, `SQLiteUpdateDeleteLimitPlan`, and the existing row-array current-source executor to cover statement-level ABORT behavior inside a savepoint.

## Next

A follow-up can wire this ABORT/savepoint current-source distinction into broader parser-level DML execution once the native UPDATE/DELETE executor grows beyond the bounded row-array model.
