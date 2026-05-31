# Real Upstream Corpus Pager/WAL Dynamic 20260531T053019Z

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`

This isolated handoff adds `SQLiteRealUpstreamPagerWalDynamic20260531T053019ZTest.php`, a focused real upstream pager/WAL corpus batch with 1002 distinct TestRunner PASS cases and 33002 behavior assertions.

Hydrated upstream source files and sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal6.test`: `wal6-1.0..1.3` journal-mode VACUUM and WAL mode transitions.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal7.test`: `wal7-1.0..4.0` WAL-index invalidation, reader snapshots, checkpoint visibility, and writer reuse.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walbig.test`: `walbig-1.0..1.3` large WAL frame indexing and recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager2.test`: `pager2-1.*..3.1` locking-mode rollback persistence, journal-tail preservation, and cache-spill state.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal1.test`: `journal1-1.1..1.2` hot rollback-journal recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal2.test`: `journal2-1.1..2.4` persistent/delete/truncate journal lifecycle.

Behavior exercised:

- WAL transaction recovery trims uncommitted valid tails and checksum/salt/truncated corrupt tails after the committed prefix.
- Reader snapshots and checkpoint reader visibility preserve pinned page images across passive/full/restart/truncate/noop checkpoint plans.
- Durable checkpoint results expose database page counts, WAL sidecar action, and WAL byte lengths for recovered committed prefixes.
- Persistent WAL close planning preserves or truncates the sidecar according to the reader boundary and size limit.
- Both big-endian and little-endian WAL checksum modes are covered across 512-byte through 16384-byte pages.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T053019ZTest.php`
- Result: `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T053019ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T053019ZTest.php`
- Result: `1 test files, 33002 assertions, 0 failures`, with 1002 PASS lines.

Expected dashboard movement if accepted:

- `phpPass`: `2297185 -> 2298187` (`+1002`)
- `phpFail`: unchanged at `0`
- mapped denominator: unchanged at `1589 / 1589`

Non-overlap note:

- This does not repeat accepted pager/WAL mode-persist, hash sidecar, invalid page-size, rollback/savepoint dynamic, warm-body, lock recovery, noop/checksum/restart/overwrite/crash, full-sync, blocking checkpoint, VFS writer/sync/lock/rollback, checkpoint transaction, or rollback-journal commit batches. It targets separate hydrated sections in `wal6.test`, `wal7.test`, `walbig.test`, `pager2.test`, `journal1.test`, and `journal2.test`.

Dependency-closure note:

- No new support component is needed. This reuses native `SQLiteWal`, `SQLiteWalHeader`, checkpoint, durable checkpoint, reader visibility, and persistent-WAL close behavior.
