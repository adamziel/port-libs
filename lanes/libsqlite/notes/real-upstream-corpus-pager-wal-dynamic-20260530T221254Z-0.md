# real-upstream-corpus-pager-wal-dynamic-20260530T221254Z-0

Status: ready.

Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`.

This slice adds `SQLiteRealUpstreamPagerWalProtocolNoShmDynamicTest.php` with
1,001 distinct focused TestRunner PASS cases and 14,001 assertions. The cases
are sourced from the hydrated upstream SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test`
  - `walprotocol-1.1..1.5`: WAL recovery lock order, protocol retry exhaustion,
    and readmark-range busy fallback.
  - `walprotocol-2.5..2.8`: concurrent reader during recovery unlock observes
    the recovered WAL contents.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test`
  - `walnoshm-1.2..1.11`: version-1 VFS WAL entry requires exclusive locking
    and deletes the WAL after rollback-mode transition.
  - `walnoshm-2.1..2.2`: copied WAL without SHM requires exclusive access and
    failed exclusive transition leaves no pending lock.
  - `walnoshm-3.1..3.2`: normal-mode downgrade depends on whether exclusive was
    set before or after opening the WAL.

The test exercises existing lane behavior through `SQLiteWal` transaction
recovery boundaries and `SQLiteVfsLockState` lock conflict application. It does
not add metadata-only rows, fake upstream script ids, or domain-specific
libsqlite API.

Non-overlap:

- Prior accepted pager/WAL dynamic batches cover `wal.test`, `wal2.test`,
  `wal3.test`, `walcksum.test`, `walrestart.test`, `walpersist.test`,
  `walsetlk*.test`, `walblock.test`, pager lock races, checkpoint/fullsync
  matrices, multi-transaction clusters, and WAL recovery batches.
- This batch owns the remaining `walprotocol.test` protocol-retry behavior and
  `walnoshm.test` no-SHM exclusive-mode transition matrix.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolNoShmDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolNoShmDynamicTest.php`
  passed with `1 test files, 14001 assertions, 0 failures`.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local WAL
parsing, transaction recovery, and VFS lock-state components.
