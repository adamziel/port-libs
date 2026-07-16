# full-run-parity-application-wal-rollback-json-dynamic-20260531T031640Z-0

Base accepted HEAD: `148cfd0e2c7cc75dba20ff0e424e615192f1e7c6`.

## Behavior

Extended `SQLiteJsonImportRollbackWalPlan` with dynamic deferred-failure
scenarios for application JSON imports in WAL mode. The existing dynamic parity
coverage only exercised caller-driven rollback of the whole JSON batch after a
malformed statement. This slice adds the paired SQLite behavior where the
malformed statement rolls back through its statement journal, but the caller
does not roll back the outer savepoint batch yet.

The new scenarios prove that applied JSON/JSONB rows remain in the imported
database image, the original WAL byte stream is not truncated, existing WAL
frame counts are preserved, and the full savepoint rollback preview remains
available if the caller later chooses to abandon the batch.

## Evidence

Before this slice:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php
1 test files, 608 assertions, 0 failures
```

After this slice:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php
1 test files, 1022 assertions, 0 failures

php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test
application-wal-rollback-json-dynamic-parity self-test passed
```

Expected focused PASS-line movement: `+414`, from `1797288` to `1797702`.

## Non-Overlap

This does not repeat accepted WAL byte truncation, VFS savepoint rollback
application, rollback-journal apply/commit, JSON visible/hidden constraint
pushdown, JSON table cursor/source wiring, pager WAL dynamic corpus rows, or
the previous whole-batch app-WAL rollback parity path. It adds the
non-overlapping no-batch-rollback caller mode on top of the same bounded JSON
import/savepoint primitive.

## Dependency Closure

No new support component is needed. The slice reuses existing JSON mutation,
JSONB value, statement-journal rollback, savepoint rollback preview, and WAL
byte-state validation primitives.
