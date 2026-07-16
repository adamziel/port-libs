# real-upstream-corpus-pager-wal-dynamic-20260530T185834Z-0

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`.

Added `SQLiteRealUpstreamPagerWalRestartOverwriteDynamicTest.php` with 1,000 generated-but-behavioral focused cases plus one upstream provenance case. The cases are sourced from the hydrated upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
  - `walrestart-1.0` creates/checkpoints a WAL with a large frame count.
  - `walrestart-1.1` checkpoints a large update.
  - `walrestart-1.2` covers the mxFrame/nBackfill restart race where a smaller concurrent WAL appears while checkpoint state is observed.
  - `walrestart-1.4` checkpoints the restarted smaller WAL.
  - `walrestart-1.5` verifies integrity after the race.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/waloverwrite.test`
  - `1.*` repeatedly updates the same pages under WAL mode.
  - `1.*` verifies recovery observes the last committed image.
  - `1.*` verifies rolled-back savepoint tail frames do not replace committed images during recovery/checkpoint materialization.

Focused result:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRestartOverwriteDynamicTest.php`

`1 test files, 21001 assertions, 0 failures`

Countable movement:

- Focused PHP PASS-line growth: `+1001`.
- Behavior assertions: `21001`, satisfying the 5000+ assertion hard floor.
- Mapped denominator growth: none; existing mapped coverage remains `1472 / 1589`.

Non-overlap:

This does not repeat accepted noop checkpoint, WAL mode persist, exclusive locking, recovery dynamic, lock-race, savepoint rollback, checkpoint sync, or sync-matrix corpus tests. It adds restart-race checkpoint reset blocking and repeated-page overwrite/savepoint-tail recovery assertions from separate upstream files.

Dependency closure:

No new support component is needed. The batch reuses existing native `SQLiteWal`, `SQLiteWalHeader`, WAL checksum parsing, frame commit detection, and checkpoint materialization.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRestartOverwriteDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRestartOverwriteDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
