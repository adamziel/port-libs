# real-upstream-corpus-pager-wal-dynamic-20260531T035039Z-0

Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`.

Added `SQLiteRealUpstreamPagerWalDynamic20260531T035039ZTest.php`, an additive real upstream pager/WAL dynamic corpus file. It cites hydrated upstream SQLite sections from:

- `wal2.test`: `wal2-6.4.*`, `wal2-6.6.*`, `wal2-10.1.*`, `wal2-10.2.*`, `wal2-11.*`, `wal2-12.*`, `wal2-13.*`, `wal2-14.*`
- `walrestart.test`: `walrestart-1.*`, `walrestart-2.*`
- `walsetlk_snapshot.test`: `walsetlk_snapshot-1.*`, `walsetlk_snapshot-2.*`
- `pager1.test`: `pager1-3.*`, `pager1-4.*`, `pager1-5.*`, `pager1-7.*`

Behavior covered: WAL transaction recovery boundaries, corrupt checksum/salt/truncated tails, uncommitted valid tail discard, reader snapshot frame pinning, checkpoint mode/durable action parity, and pager savepoint/hot-journal/multi-file/truncate-journal visibility.

Focused evidence:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T035039ZTest.php`

Result: `1 test files, 37575 assertions, 0 failures`, with `1001` PASS lines.

Expected selected movement: `+1001` focused PASS lines, from `1932390` to `1933391` pass / `0` fail. Mapped denominator coverage stays `1589 / 1589`.

Dependency closure: no new support component is needed; this reuses lane-local `SQLiteWal` parser, transaction recovery, reader snapshot, checkpoint, durable checkpoint, and checkpoint reader visibility primitives.

Non-overlap: this does not add production APIs and does not repeat accepted pager WAL hook protocol/dynamic, WAL overwrite/restart, readonly-SHM refresh, page-size mapping, VFS rollback/commit/sync/lock writer, or savepoint byte-truncation implementation surfaces. It is a focused dynamic corpus expansion against distinct upstream section names.
