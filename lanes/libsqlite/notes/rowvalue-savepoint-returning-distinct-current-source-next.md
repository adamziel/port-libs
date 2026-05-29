# Row-value savepoint RETURNING DISTINCT current-source next148

Status: focused PHP behavior growth for `rowvalue-savepoint-returning-distinct-current-source-next`.

This slice adds `SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan`, a bounded native PHP savepoint model for copied `wp_options` import cleanup statements that use row-value `IS DISTINCT FROM` and `IS NOT DISTINCT FROM` predicates with UPDATE/DELETE RETURNING. The plan records de-duplicated RETURNING stream keys by row-value columns, preserves released savepoint changes, discards attempted inner RETURNING rows after rollback, and retries from the restored current source.

Non-overlap: avoids accepted next143 row-value conflict retry and next144 DELETE-only rollback surfaces. This next148 slice is about row-value DISTINCT/NOT DISTINCT predicates plus RETURNING stream de-duplication across rollback/retry.

WordPress smoke: `examples/wordpress-rowvalue-savepoint-returning-distinct-current-source-next.php` models copied option cleanup where stale cache rows are reviewed, an inner attempted retry rolls back, and the final retry starts from the restored current source.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSavepointReturningDistinctCurrentSourceNextTest.php
```

Result: `1 test files, 64 assertions, 0 failures`.

Dependency closure: no new support component needed; reuses native UPDATE/DELETE RETURNING row-value predicate evaluation and savepoint current-source modeling.

Expected dashboard movement: `phpPass` +64 from focused PASS lines. Mapped upstream coverage remains unchanged at `606 / 1589`.
