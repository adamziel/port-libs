# Real upstream pager/WAL NOOP checkpoint dynamic corpus

Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T181419Z-0`

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
- Ported subtests: `walckptnoop-1.0` through `walckptnoop-1.10`

Behavior:

- Adds 1,000 focused dynamic cases plus one upstream-source record test for WAL
  `PRAGMA wal_checkpoint = noop` behavior.
- The cases vary page size, base database page count, committed WAL
  transactions, uncommitted WAL tail frames, and reader end-frame positions.
- Each case proves NOOP is observational: it reports WAL state without
  backfilling database pages, does not reset or truncate the WAL, preserves the
  database image, and remains non-busy when a reader would limit a non-NOOP
  checkpoint.
- Red-first evidence: the first focused run failed 600 cases because NOOP was
  incorrectly treated as reader-blocked. `SQLiteWal::checkpointModePlan()` now
  excludes NOOP from reader completion blocking and returns
  `noop_checkpoint_does_not_backfill`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoopCheckpointDynamicTest.php`
- Result: `1 test files, 20601 assertions, 0 failures`
- Focused PASS lines: `1001`

Dashboard movement:

- `phpPass`: `233897 -> 234898` (`+1001`)
- Mapped coverage: unchanged at `1189 / 1589`

Dependency closure:

- No new support component needed. The slice reuses existing native PHP WAL
  parsing, checksum validation, checkpoint mode planning, and durable
  checkpoint result primitives.

Non-overlap:

- This does not repeat accepted WAL checkpoint transaction, WAL savepoint byte
  truncation, VFS file writer, VFS sync/apply, rollback-journal commit/apply,
  WAL restart, or existing walmode/walpersist/walro dynamic coverage.
