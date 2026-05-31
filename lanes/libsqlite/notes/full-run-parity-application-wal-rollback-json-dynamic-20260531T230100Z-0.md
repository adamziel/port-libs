# full-run parity application WAL rollback JSON dynamic

Base accepted HEAD: `292ada6b86cc431f7b1537075eacedfb4e905cf4`

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T230100Z-0`

## Behavior

Added rollback-disabled reopened-prefix checkpoint parity for application JSON import WAL flows. The new scenarios start after the existing rollback-disabled failure/recovery chain and its reopened-prefix success commit, then run released and reader-pinned `restart`/`truncate` checkpoint results against the reopened WAL bytes.

The assertions cover checkpoint input hashes, final reopened commit-frame boundaries, restart/truncate sidecar shape, durable application of reopened catalog/insert/recovery pages, superseded prefix images for reused pages, reader-pinned preservation of the final commit frame, retained corrected keys, rejected rolled-back tail keys, and deterministic small-batch coverage.

## Test Growth

Baseline for `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` on this accepted worktree: `8524` TestRunner PASS cases / `13660` assertions / `0` failures.

After this slice: `8784` TestRunner PASS cases / `14446` assertions / `0` failures.

Delta: `+260` focused PASS cases and `+786` assertions. `lane-status.json` moves `phpPass` from `4198004` to `4198264`; mapped coverage remains `1589 / 1589`.

## Non-Overlap

This extends the reopened-prefix success chain with checkpoint behavior. It does not repeat post-checkpoint tail failure/recovery, post-recovery checkpoint, JSON table generated path rowid-cost behavior, VFS rollback-journal apply, rollback-journal commit, super-journal commit, VFS sync/apply, VFS lock-state/file-lock/locked-writer behavior, B-tree page move/root-collapse/overflow-freelist release, parser-level JSON table SELECT sources, trigger/FK dynamic corpus, window string_agg dynamic corpus, or SQL GROUP BY/subquery/comma-LIMIT paths.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local `SQLiteWal`, `SQLiteJsonImportRollbackWalPlan`, and `SQLiteJsonImportSavepointPlan` support paths.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`: no syntax errors.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`: `1 test files, 14446 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`: `application-wal-rollback-json-dynamic-parity self-test passed`.

Root harness: not run - isolated micro-slice.
