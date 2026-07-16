# full-run-parity-application-wal-rollback-json-dynamic-20260531T060847Z-0

Base accepted HEAD: `cd24ba2f7b741bb89ced6cb6c27264084794565b`.

Implemented an additive application WAL rollback JSON dynamic parity slice in
`SQLiteJsonImportRollbackWalPlan`: successful retry batches can now opt in to
materializing corrected JSON mutations back into the WAL byte stream after a
failed batch has rolled back. The default non-materializing retry behavior is
unchanged.

Focused behavior:

- Empty-prefix retry batches append corrected page frames after rollback to the
  WAL header.
- Preexisting-prefix retry batches preserve the committed prefix bytes and
  append contiguous corrected page frames after that prefix.
- Materialized frame payloads are copied from the corrected post-import
  database image, and non-contiguous success frame indexes are rejected.

Verification:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed with `1 test files, 4070 assertions, 0 failures`.

Assertion delta:

- Before: `3854` assertions for the focused file on this base.
- After: `4070` assertions.
- Focused growth: `+216` assertions.

Non-overlap:

- Does not repeat accepted application WAL rollback JSON parity rows, missing
  WAL tail rejection, partial tail rejection, frame-header mismatch rejection,
  VFS savepoint rollback apply, rollback-journal commit apply, WAL byte
  truncation, or JSON dynamic corpus behavior. This slice specifically covers
  opt-in byte-stream materialization for successful retry frames after a failed
  dynamic JSON WAL batch rollback.

Dependency closure:

- No new support component is needed. The slice reuses existing JSON mutation,
  JSONB, savepoint rollback, WAL header/frame, and page-image primitives.
