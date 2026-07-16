# real-upstream-corpus-pager-wal-dynamic-setlk-blocking-20260530T210555Z

Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`

Implemented a bounded real upstream pager/WAL dynamic cluster for blocking WAL
locks and reader waits. The source truth is the hydrated SQLite upstream corpus:

- `walsetlk.test`: sections `1.0..1.8` and `2.*`
- `walsetlk2.test`: sections `1.3..1.5` and `2.0..2.7`
- `walblock.test`: sections `1.1.*` and `1.2.*`

The new plan matrix records writer/write-lock conflicts, checkpoint waits on
readers, shared-memory readmark lock order, setlk timeout expiry, WAL-index
update waits, and reader visibility after checkpoint state stabilizes. It
varies checkpoint mode, journal persistence, sync mode, page size, timeout, and
release delay across 1,000 focused TestRunner cases.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`: no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSetlkBlockingDynamicTest.php`: no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSetlkBlockingDynamicTest.php`: 1 test files, 17011 assertions, 0 failures, 1001 PASS lines

Expected dashboard movement:

- `phpPass`: `718526 -> 719527` if accepted
- mapped denominator: unchanged at `1589 / 1589`
- full SQLite release/all parity: not claimed

Non-overlap:

This slice does not repeat accepted pager/WAL mode-persist, readonly checkpoint,
restart-noop, overwrite, crash recovery, WAL checksum, WAL byte truncation, VFS
writer, rollback-journal apply, or checkpoint transaction clusters. It targets
the `walsetlk*` blocking-lock and `walblock` reader-wait behavior that was only
present as broad suite inventory before this handoff.

Dependency closure:

No new support component is needed. The batch reuses existing lane-local
pager/WAL dynamic plan data and TestRunner coverage; it does not require live
process locks, external services, or upstream testfixture execution.
