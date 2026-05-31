# real-upstream-corpus-vfs-io-dynamic-20260531T011746Z-0

Added `SQLiteRealUpstreamCorpusVfsMemoryJournalDynamicTest.php` as a real upstream VFS I/O dynamic corpus batch for in-memory rollback journals and nested savepoint loops.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal2.test`
- Scenario names: `memjournal.test 1.0-1.3`, `memjournal2.test 1.0`, `memjournal2.test 1.1`, and `memjournal2.test 1.2.200-1.2.300`.

Focused behavior:

- `PRAGMA journal_mode=memory` keeps rollback images in memory rather than creating disk journal writes.
- Outer savepoint images scale with the touched row prefix.
- Repeated inner savepoint updates keep only the inner row image per repeat and restore row one on rollback.
- Outer rollback clears the touched rows; outer commit preserves the touched prefix.

Focused assertion count:

- 1000 dynamic behavior cases plus source and malformed-input guards.
- Verified focused assertions: 21010.

Non-overlap:

- This ports the memory-journal savepoint loop surface. It does not repeat appendvfs offset/grow/update coverage, cksumvfs reserve-byte/WAL coverage, sysfault, ioerr pointer-map, ioerr2/ioerr3/ioerr4 recovery, atomic crash/admission, walvfs SHM fault, mmap, quota, safe-delete journal, pagerfault large rollback, VFS file writer, locked writer, sync apply, rollback-journal apply/commit, or WAL savepoint byte truncation coverage.

Dependency closure:

- No new support component is needed. The test reuses `SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile()`.
