# WAL reader checkpoint boundary current next19

Status: focused PHP behavior growth for WAL reader visibility across a savepoint rollback checkpoint boundary.

## Delta

- Added `SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo()` to compose the existing savepoint WAL current-prefix planner with durable checkpoint output and explicit current-reader versus next-reader page visibility.
- Current readers keep the retained WAL snapshot at the checkpoint boundary, while next readers after RESTART/TRUNCATE see the checkpointed database image/reset WAL state.
- Busy restart checkpoints with a current reader preserve the retained WAL for next readers, matching the existing checkpoint busy/reset semantics.
- Added `SQLiteWalReaderCheckpointBoundaryCurrentNext19Test.php` with 58 independent PASS cases.
- Added `application-wal-reader-checkpoint-boundary.php` to smoke a copied `wp_options` plugin-settings rollback followed by a truncate checkpoint boundary without `ext/sqlite`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointBoundaryCurrentNext19Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-reader-checkpoint-boundary.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointBoundaryCurrentNext19Test.php`: 1 test files, 58 assertions, 0 failures, 58 PASS lines.
- `php lanes/libsqlite/examples/application-wal-reader-checkpoint-boundary.php --self-test`: passed; reported retained WAL sources for the current reader, checkpoint-database sources for the next reader, matching images, `truncate_wal`, and no rolled-back plugin frame visible.

## Status

- `phpPass`: +58, from 6444 to 6502 in this isolated worktree.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit is claimed.

## Non-overlap

Avoids accepted/queued WAL byte truncation-only diagnostics, VFS savepoint rollback application, rollback-journal commit/apply, WAL checkpoint transaction planning, WAL SHM read-mark locking, durable checkpoint byte materialization, VFS writer/sync/lock clusters, SQL/JSON/B-tree/encoding corpus work, and the previous `next15` current-prefix checkpoint planner. This slice specifically covers the current-reader/next-reader visibility boundary around that checkpoint result.

## Dependency closure

No new support component is needed. The slice reuses lane-local `SQLiteSavepointStack`, `SQLiteWal`, and durable checkpoint helpers.
