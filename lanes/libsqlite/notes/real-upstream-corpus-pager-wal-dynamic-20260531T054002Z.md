# real-upstream-corpus-pager-wal-dynamic-20260531T054002Z

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.

Added `SQLiteRealUpstreamPagerWalDynamic20260531T054002ZTest.php`, a 1000-case real upstream pager/WAL dynamic corpus plus one upstream-section inventory check. The batch is non-overlapping with the accepted `SQLiteRealUpstreamPagerWalDynamic20260531T045404ZTest.php` file by using a distinct timestamped test file and a broader section set focused on WAL format/corruption, checkpoint restart/truncate, pager cache-spill/hot-journal recovery, rollback journal boundaries, master journal recovery, and nested savepoint boundaries.

Upstream source truth from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `wal2.test`: `wal2-7.1`, `wal2-8.1`, `wal2-10.1`, `wal2-11.2`
- `wal3.test`: `wal3-2.1`, `wal3-3.0`
- `walckpt.test`: `walckpt-2.1`, `walckpt-3.1`
- `walrestart.test`: `walrestart-1.2`, `walrestart-2.1`
- `pager1.test`: `pager1-24.1`
- `pager2.test`: `pager2-1.1`
- `journal1.test`: `journal1-2.1`
- `journal2.test`: `journal2-1.3`
- `savepoint.test`: `savepoint-7.1`
- `savepoint2.test`: `savepoint2-4.1`

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T054002ZTest.php` passed.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T054002ZTest.php` passed: `1 test files, 34001 assertions, 0 failures`, with `1001` PASS lines.

Expected selected movement: `phpPass` `2323745 -> 2324746` (`+1001`) from focused PHP TestRunner PASS lines. Mapped denominator remains `1589 / 1589`.

Dependency closure: no new support component is needed; this reuses existing native PHP WAL parsing, recovery-boundary, checkpoint, durable checkpoint, reader snapshot, and multi-transaction cluster helpers.
