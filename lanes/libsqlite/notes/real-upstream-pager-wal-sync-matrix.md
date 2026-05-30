# real-upstream-pager-wal-sync-matrix

Status: focused real upstream pager/WAL corpus coverage for sync/fullsync checkpoint behavior.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-14.1` through `wal2-14.3`: checkpoint/fullsync counts around `PRAGMA wal_autocheckpoint`, overflow inserts, and close after autocheckpoint is disabled.
  - `15.1` through `15.12`: `PRAGMA checkpoint_fullfsync`, `PRAGMA fullfsync`, and `PRAGMA synchronous` restart/commit/checkpoint sync-count matrix.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
  - `1.1` through `1.10`: `PRAGMA wal_checkpoint = noop` reports log/checkpoint progress without applying a checkpoint, returns busy during an open writer transaction, and reports `0 -1 -1` outside WAL mode.

Implementation:
- Added `SQLiteWalSyncMatrix`, a source-neutral native PHP model for the upstream WAL sync-count matrix and noop checkpoint result shape.
- The red-first focused run exposed a mismatch for `wal2.test 15.8`: `checkpoint_fullfsync=1`, `fullfsync=0`, `synchronous=NORMAL`, restart phase. The implementation now emits one full sync for that case, matching upstream.
- Reuses existing `SQLiteVfsSyncPlan` constants for normal/full sync flags.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteWalSyncMatrix.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSyncMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSyncMatrixTest.php`
  - Result: `1 test files, 443 assertions, 0 failures`

Expected status delta:
- `phpPass`: `188568 -> 189011` from 443 newly passing focused real upstream assertions.
- `benchmarkDenominator.mapped`: unchanged at `958 / 1589`; this does not claim new suite denominator rows.

Non-overlap:
- This avoids the accepted real upstream date/VFS batch, WAL byte truncation, checkpoint transaction planning, rollback-journal commit/apply, VFS writer/sync apply, process locks, JSON table source/cursor/constraint work, B-tree page/freelist/overflow work, SELECT SQL text/group/order/subquery work, and Unicode GLOB coverage.
- The owned behavior is the real upstream WAL sync/fullsync/noop-checkpoint result matrix.

Dependency closure:
- No new support component is needed. The slice reuses the existing VFS sync flag constants and adds a bounded WAL decision model.

Follow-up:
- A subsequent real-corpus pager/WAL refill should extend this to another distinct upstream file or add enough adjacent real WAL/pager behavior to exceed the 500-assertion real-corpus floor.
