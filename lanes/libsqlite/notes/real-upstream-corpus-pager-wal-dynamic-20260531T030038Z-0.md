# real-upstream-corpus-pager-wal-dynamic-20260531T030038Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T030038Z`

Base accepted HEAD: `57904efd88f87abfad6d70c753ea59660958850e`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal8.test`
- Ported sections: `wal8.test` `1.1`, `2.1`, and `3.1`.

Behavior added:

- Adds `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal8EmptyFilePageSizeRows()`.
- Adds 1,000 distinct dynamic rows for the upstream WAL edge where one
  connection opens an empty database file, a peer initializes the same file as
  WAL, then the first connection runs `PRAGMA page_size=4096` before `VACUUM`
  or reading `sqlite_master`.
- Adds one focused citation/count assertion for the hydrated `wal8.test`
  section ranges.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - `1 test files, 75884 assertions, 0 failures`
  - Focused PASS growth: `+1001` TestRunner cases from real upstream `wal8.test`.

Expected status delta:

- `phpPass`: `1760993 -> 1761994`
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`

Non-overlap:

- This slice extends the real upstream pager/WAL dynamic corpus with `wal8.test`
  empty-file page-size behavior only.
- It avoids accepted `wal2`, `wal3`, `walhook`, `walpersist`, `waloverwrite`,
  `walrestart`, `walro`, `walmode`, `walsetlk`, `walprotocol`, `walckptnoop`,
  WAL checkpoint transaction, VFS writer/sync/lock, rollback-journal apply,
  savepoint byte truncation, pager master-journal, JSON, SELECT, B-tree,
  trigger, PRAGMA, and encoding clusters.

Dependency closure:

- No new support component is needed. The slice reuses the existing lane-local
  pager/WAL dynamic corpus-plan structure.

Next task:

- Continue pager/WAL corpus only on a non-overlapping upstream file or behavior
  section, such as remaining fault/protocol/open-boundary cases not already
  represented by accepted dynamic corpus slices.
