# real-upstream-corpus-pager-wal-dynamic-20260530T194843Z-0

Added a focused real-upstream pager/WAL follow-up corpus under
`SQLiteRealUpstreamPagerWalDynamicFollowupCorpusTest.php`.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`

Scenarios ported:
- `wal.test wal-1.*` WAL commit frames, reader-pinned restart checkpoints, and stable reader visibility.
- `walcksum.test walcksum-*` checksum byte order and committed-prefix recovery before corrupt tails.
- `wal2.test wal2-*` no-op/passive/truncate checkpoint mode effects over committed WAL frames.
- `pager1.test pager1-*` hot rollback-journal detection, checksum-validated page records, and rollback to the initial database image.

Focused assertion count:
- 1001 distinct TestRunner cases.
- 9251 behavior assertions.

Non-overlap:
- This file does not repeat accepted `walckptnoop` no-op-only coverage, WAL byte-truncation savepoint coverage, rollback-journal commit/apply coverage, or pager reader-cache numbered handoffs. It combines commit-boundary, corrupt-tail recovery, checkpoint backfill/truncate, and rollback-journal image restoration across dynamic upstream-named cases.

Dependency closure:
- No new support component is needed. The slice reuses existing native PHP `SQLiteWal` and `SQLiteRollbackJournal` primitives.
