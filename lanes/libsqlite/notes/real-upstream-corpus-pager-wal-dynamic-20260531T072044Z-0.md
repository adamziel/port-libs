# Real upstream pager/WAL dynamic corpus 072044

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T072044Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal5.test`
- Ported `wal5.test` blocking-checkpoint behavior from the `1.$tn`,
  `2.1` through `2.4`, `3.$tn`, `4.$tn`, and `5.$tn` sections.

Behavior covered:

- PRAGMA and C API checkpoint entry points share the same PASSIVE/FULL/RESTART/
  TRUNCATE result matrix.
- FULL, RESTART, and TRUNCATE rows preserve upstream busy-handler release
  points for writer locks, partial readers, and any WAL reader.
- Attached-database checkpoint rows retain the upstream distinction between
  main and aux WAL backfill results.
- TRUNCATE checkpoints distinguish reader-blocked preservation from successful
  zero-length WAL sidecar results.
- Each row also exercises native `SQLiteWal` parsing, checkpoint result,
  durable checkpoint bytes, and reader visibility behavior.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T072044ZTest.php`
  - `1 test files, 34812 assertions, 0 failures`
  - `1202` focused TestRunner PASS cases.

Non-overlap:

- This avoids accepted `walckptnoop`, `walhook`, `walnoshm`, `wal3` rollback
  hash, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/
  lock, pager1 boundary, checkpoint transaction, walpersist, and WAL checksum
  batches. The new surface is upstream `wal5.test` blocking-checkpoint
  lock/busy/truncate matrix behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native
  `SQLiteWal` checkpoint, durable checkpoint, and reader visibility primitives
  against hydrated upstream SQLite `wal5.test` source truth.

Root harness:

- Not run - isolated micro-slice.
