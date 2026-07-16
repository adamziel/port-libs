# Real Upstream Corpus Pager/WAL Dynamic 20260531T024301Z

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`

This isolated handoff adds `SQLiteRealUpstreamCorpusPagerWalDynamic20260531T024301ZTest.php`, a focused real upstream pager/WAL corpus batch with 1001 distinct TestRunner PASS cases and 32001 behavior assertions.

Hydrated upstream source files and sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test`: `walcksum-1.*` checksum-damaged WAL tail recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`: `walrestart-1.*` restart checkpoint reader-boundary preservation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/waloverwrite.test`: `waloverwrite-1.*` newest committed WAL frame wins for overwritten pages.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcrash.test`: `walcrash-1.*` and `walcrash-2.*` uncommitted/corrupt WAL tail discard.

Behavior exercised:

- WAL checksum recovery trims corrupt frame tails after the committed prefix.
- Transaction recovery discards uncommitted valid tails before corrupt frames.
- Reader snapshots select the latest committed frame visible to the reader boundary.
- Checkpoint and durable checkpoint plans preserve WAL bytes while readers pin the committed prefix.
- Repeated overwritten page frames resolve to the newest committed page image.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T024301ZTest.php`
- Result: `1 test files, 32001 assertions, 0 failures`, with 1001 PASS lines.

Expected dashboard movement if accepted:

- `phpPass`: `1726669 -> 1727670` (`+1001`)
- `phpFail`: unchanged at `0`
- mapped denominator: unchanged at `1589 / 1589`

Non-overlap note:

- This does not repeat accepted pager/WAL mode-persist, WAL hash sidecar, rollback/savepoint dynamic, checksum persistence, readonly-SHM, noop-checkpoint, or real-pager boundary batches. It targets a separate upstream cluster around `walcksum`, `walrestart`, `waloverwrite`, and `walcrash` recovery/checkpoint behavior.

Dependency-closure note:

- No new support component is needed. The test reuses existing native PHP `SQLiteWal` and `SQLiteWalHeader` behavior plus existing TestRunner/autoload support.
