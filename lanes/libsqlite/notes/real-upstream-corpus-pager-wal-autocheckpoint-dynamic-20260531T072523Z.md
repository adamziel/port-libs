# real-upstream-corpus-pager-wal-dynamic-20260531T072523Z-0

Added `SQLiteRealUpstreamPagerWalAutoCheckpointDynamicTest.php` with a real
upstream pager/WAL auto-checkpoint dynamic corpus.

Upstream source truth:

- `e_walauto.test` `R-38128-34102`, `R-33626-48418`, `R-30135-06439`,
  `R-17497-43474`, and `R-52669-10547`
- `walhook.test` `walhook-1.1`, `walhook-1.4`, `walhook-1.5`, and
  `walhook-2.*`
- `e_walckpt.test` `R-62028-47212`, `R-29177-48281`, and `R-03996-12088`
- `wal7.test` `wal7-1.*` through `wal7-4.*`

Focused coverage:

- 1000 dynamic TestRunner PASS cases plus 2 citation/non-overlap cases.
- 36019 focused assertions over WAL frame construction, committed transaction
  boundaries, transaction recovery, auto-checkpoint threshold state,
  hook/autocheckpoint replacement state, checkpoint mode output,
  durable-checkpoint sidecar output, reader snapshots, and persistent WAL close
  behavior with journal-size-limit inputs.

Non-overlap:

- This does not repeat accepted pager/WAL snapshot boundary, invalid page-size,
  hash sidecar, lock-race, persist-mode, readonly-SHM, checkpoint transaction,
  rollback-journal commit/apply, savepoint rollback, WAL byte-truncation, or
  VFS writer/sync/lock batches.
- This slice owns the auto-checkpoint threshold and WAL-hook replacement
  boundary cluster from the hydrated upstream corpus.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  `SQLiteWal`, `SQLiteWalHeader`, transaction-recovery, checkpoint-mode,
  durable-checkpoint, reader-snapshot, and persistent-WAL close primitives.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalAutoCheckpointDynamicTest.php`
  - `1 test files, 36019 assertions, 0 failures`

Root harness: not run - isolated micro-slice.
