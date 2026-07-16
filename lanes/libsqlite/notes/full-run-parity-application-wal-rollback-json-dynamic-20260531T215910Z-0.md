# full-run parity application WAL rollback JSON dynamic

Base accepted HEAD: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T215910Z-0`

## Behavior

Added full-run checkpoint parity for application JSON import WAL rollback flows. The new scenarios derive from the existing fail/rollback -> retry -> followup materialized WAL chain, then run released and reader-pinned `restart`/`truncate` checkpoint results against the followup WAL bytes.

The assertions cover checkpoint input hashes, followup commit-frame boundaries, restart/truncate sidecar actions, released checkpoint page materialization for retry and followup pages, superseded retry catalog frames, reader-pinned busy preservation, final followup page pinning, retained corrected keys, and deterministic small-batch coverage.

## Test Growth

Captured baseline for `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`: `8173` TestRunner cases / `12819` assertions / `0` failures.

After this slice: `8524` TestRunner cases / `13660` assertions / `0` failures.

Delta: `+351` focused PASS cases and `+841` assertions. `lane-status.json` moves `phpPass` from `3849202` to `3849553`; mapped coverage remains `1589 / 1589`.

## Non-Overlap

This does not touch JSON table generated path rowid-cost behavior, rollback-disabled post-checkpoint tail chains, VFS rollback-journal apply, rollback-journal commit, super-journal commit, VFS sync/apply, VFS lock-state/file-lock/locked-writer behavior, B-tree page move/root-collapse/overflow-freelist release, parser-level JSON table SELECT sources, or SQL GROUP BY/subquery/comma-LIMIT paths.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteWal`, `SQLiteJsonImportRollbackWalPlan`, and `SQLiteJsonImportSavepointPlan` support paths.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`: no syntax errors.
- `python3 -m json.tool lanes/libsqlite/lane-status.json`: valid JSON.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`: `1 test files, 13660 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`.
- `php -d memory_limit=2048M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`: `application-wal-rollback-json-dynamic-parity self-test passed`.
- `git diff --check -- lanes/libsqlite`: passed.

Root harness: not run - isolated micro-slice.
