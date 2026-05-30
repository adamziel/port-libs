# SQLite row-value UPDATE/DELETE RETURNING savepoint current-source next161

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext161Plan`.
- Models `UPDATE OR FAIL` row-value assignment inside an import savepoint where an earlier row yields `RETURNING`, a later row hits a unique conflict, and the caller explicitly `ROLLBACK TO` the savepoint before retrying UPDATE/DELETE RETURNING statements.
- The failed attempt's partial current source and RETURNING stream are reported as discarded evidence; retry statements execute against the restored savepoint image.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext161Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-rowvalue-fail-rollback-retry-current-source-next161.php`
- Dependency closure: no new support component needed; this composes existing native PHP row-value UPDATE/DELETE RETURNING and savepoint current-source modeling.

## Non-overlap

This does not repeat accepted next132 `OR FAIL` savepoint preservation without rollback, next156-next158 row-value retry/rollback behavior without `OR FAIL`, or accepted WAL/pager/B-tree current-source slices. The added behavior is the explicit `ROLLBACK TO` discard/retry path after a partial `OR FAIL` row-value RETURNING statement.
