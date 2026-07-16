# real-upstream-corpus-pager-wal-dynamic-20260530T194322Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Implemented a focused real-upstream pager/WAL dynamic matrix over existing
hydrated SQLite corpus mappings from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  (`wal2-1.*`, `wal2-2.*`, `wal2-3.*`, `wal2-6.*`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  (`pager1-*` lock transition and busy writer/reader cases)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
  (`walrestart-1.*` checkpoint race cases)

Changed files:

- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicMatrixTest.php`

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicMatrixTest.php`
  passed: `1 test files, 17903 assertions, 0 failures`.

Coverage delta:

- Adds 1025 focused TestRunner PASS cases in a new pager/WAL dynamic test file.
- Assertion growth from the new file is 17903 focused assertions.
- The batch is non-overlapping with existing
  `SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php` and
  `SQLiteRealUpstreamPagerWalExclusiveDynamicTest.php`: it composes their real
  upstream WAL/pager source cases into a broader connection/checkpoint/sync/page
  size matrix and verifies lock, recovery, checkpoint, journal/WAL exclusivity,
  source-file, and dependency invariants.

Dependency closure:

- No new support component is needed. The patch reuses the existing generic
  `SQLiteRealUpstreamPagerWalDynamicPlan` source model and TestRunner harness.

Root harness:

- Not run - isolated micro-slice.
