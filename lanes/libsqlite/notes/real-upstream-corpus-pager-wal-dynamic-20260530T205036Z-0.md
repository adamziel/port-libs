# real-upstream-corpus-pager-wal-dynamic-20260530T205036Z-0

Added `SQLiteRealUpstreamPagerWalDynamicMatrixTest.php`, a 1001-case focused
PHP TestRunner batch based on hydrated upstream SQLite pager/WAL corpus files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  `wal-1.*`, `wal-2.*`, `wal-3.*`, and `wal-4.*`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
  `wal3-2.*` and `wal3-6.*`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test`
  `walcksum-1.*` and `walcksum-2.*`.

The batch exercises native `SQLiteWal` checksum parsing, transaction recovery
boundaries, committed-prefix checkpointing, reader visibility, endian checksum
continuity, corrupt tail recovery, salt mismatch recovery, truncated tails, and
uncommitted WAL tails. It is non-overlapping with the accepted
`SQLiteRealUpstreamPagerWalModePersistDynamicTest.php` coverage, which targets
`walmode.test`, `walpersist.test`, `walro.test`, and `walro2.test`.

Dependency closure: no new support component is needed. The slice reuses the
existing native WAL parser, checkpoint, and reader-visibility primitives.
