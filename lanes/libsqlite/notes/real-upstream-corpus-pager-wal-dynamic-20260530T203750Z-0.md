# Real upstream corpus pager WAL dynamic blocker

Scope: `real-upstream-corpus-pager-wal-dynamic-20260530T203750Z-0`.

Current accepted base: `80c609b1de0bbfd42f2c3e021c00d868ce6dbc14`.

I inspected the hydrated upstream SQLite pager/WAL corpus under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`, specifically
`pager1.test`, `wal.test`, `wal2.test`, `walcksum.test`, `walckptnoop.test`,
`waloverwrite.test`, `walrestart.test`, `walpersist.test`, `walcrash*.test`,
and `walvfs.test`.

This slice is blocked as a ready behavior patch by the hard handoff floor. The
current base already contains broad real upstream pager/WAL coverage in
`SQLiteRealUpstreamPagerWalDynamicPlan`,
`SQLiteRealUpstreamPagerWalDynamicCorpusPlan`, and focused tests including:

- `SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`
- `SQLiteRealUpstreamPagerWalDynamicExtendedCorpusTest.php`
- `SQLiteRealUpstreamPagerWalDynamicFollowupCorpusTest.php`
- `SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `SQLiteRealUpstreamPagerWalNoopCheckpointDynamicTest.php`
- `SQLiteRealUpstreamPagerWalOverwriteDynamicTest.php`
- `SQLiteRealUpstreamPagerWalSavepointRollbackCorpusTest.php`
- `SQLiteRealUpstreamCorpusPagerWalWarmBodyDynamicTest.php`
- `SQLiteRealUpstreamCorpusVfsWalShmDynamicTest.php`

The obvious upstream sections overlap existing accepted coverage:

- `wal.test` `wal-0.1`, `wal-1.*`, `wal-2.*`, `wal-3.*`, and `wal-4.*` are
  already represented by warm-body, MVCC, rollback, and savepoint rollback
  corpus tests.
- `wal2.test` `wal2-1.*` through `wal2-6.*` are already represented by
  header recovery, stale header, busy recovery, exclusive locking, checkpoint
  recovery-lock, and SHM-open cases.
- `walckptnoop.test` is already represented by noop checkpoint dynamic tests.
- `walcksum.test` is already represented by checksum prefix and dynamic WAL
  checksum corpus tests.
- `waloverwrite.test`, `walrestart.test`, `walpersist.test`, and `walvfs.test`
  are already represented by overwrite/restart/persist/VFS SHM dynamic tests.

I did not add a convenience-sized patch because the remaining non-overlapping
pager/WAL candidates I found would be small or would require repetitive matrix
expansion around already-accepted records. That would violate the current hard
floor for `real-upstream-corpus-*` slices: at least 1,000 distinct focused
TestRunner PASS cases, 5,000 behavior assertions, a blocker fix that unlocks
at least 2,000 PASS cases or 10,000 assertions, or guarded mapped denominator
movement.

Next larger batch to try:

- Run a guarded upstream-runner admission slice over the remaining unmapped
  pager/WAL files as an evidence batch rather than another PHP matrix wrapper.
- Candidate command family: use the existing bounded SQLite Tcl runner from
  the main repo against the hydrated cache with a pager/WAL selector such as
  `pager*.test wal*.test`, excluding the files already admitted by current PHP
  corpus coverage.
- Count it only if the runner produces real zero-error upstream rows or exposes
  one concrete runner/parser blocker whose fix unlocks at least 2,000 PASS rows
  or 10,000 assertions.

Dependency closure: no new support component is proposed from this blocked
slice. The blocker is coverage overlap plus throughput floor, not a missing
native PHP support library.

Root harness: not run - isolated micro-slice.
