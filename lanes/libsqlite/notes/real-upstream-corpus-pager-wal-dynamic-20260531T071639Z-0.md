# real-upstream-corpus-pager-wal-dynamic-20260531T071639Z-0

Added a real upstream pager/WAL dynamic corpus batch for:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal4.test`
- `wal4-1.1` creates a WAL database and rows `1 2`
- `wal4-1.2` saves only WAL/SHM sidecars and removes the database file
- `wal4-1.3` reopens the empty database and must not replay the orphan WAL
- `wal4-2` faultsim keeps the database at zero bytes and deletes the WAL only
  after a successful schema read

Implementation:

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal4EmptyDatabaseWalRows()`
  with 1,000 deterministic fault variants over the upstream empty-db/orphan-WAL
  recovery behavior.
- Extended `SQLiteRealUpstreamCorpusPagerWalDynamicTest.php` with two focused
  TestRunner cases per row plus a hydrated-upstream citation guard.
- New focused PASS-line growth: `+2001` TestRunner PASS cases.
- Focused assertion count after change: `133095 assertions`.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php

php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php
1 test files, 133095 assertions, 0 failures
```

Non-overlap:

This slice does not repeat the accepted readonly no-SHM, setlk snapshot,
walcheckpoint, walrestart, walro/walro2, wal5 blocking checkpoint, VFS writer,
rollback-journal apply, savepoint rollback, or WAL byte-truncation clusters.
It targets `wal4.test`, which was not represented in the existing dynamic
pager/WAL corpus.

Dependency closure:

No new support component is needed. The batch reuses the existing
`SQLiteRealUpstreamPagerWalDynamicCorpusPlan` dynamic corpus model and records
the existing `sqlite-pager-wal-dynamic` dependency.
