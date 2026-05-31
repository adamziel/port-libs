# real-upstream-corpus-pager-wal-dynamic-20260531T233432Z-0

Implemented an additive real upstream pager/WAL corpus slice for hydrated
SQLite `wal.test` sections `wal-17.1` through `wal-17.7`.

The new helper `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walFullSyncPaddingRows()`
models the synchronous `FULL` WAL padding-frame rule: after a committed
transaction, enough valid non-committed padding frames are appended so the next
transaction starts in a disk sector that does not contain bytes from the
committed transaction. The focused test builds valid native WAL byte streams,
parses them with `SQLiteWal`, verifies checksum chains, confirms the upstream
`wal_file_size` totals for the 171-frame upstream case, and checks that padding
frames remain outside the committed checkpoint prefix.

Focused coverage:

- Upstream source files:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  and `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal_common.tcl`
- Upstream scenarios: `wal-17.1`, `wal-17.2`, `wal-17.3`, `wal-17.4`,
  `wal-17.5`, `wal-17.6`, and `wal-17.7`
- New focused TestRunner PASS cases: `1003`
- New focused assertions: `24039`
- Mapped denominator: unchanged at `1589 / 1589`

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T233432ZTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T233432ZTest.php

php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T233432ZTest.php
1 test files, 24039 assertions, 0 failures
```

Non-overlap:

This targets `wal.test` `wal-17.*` synchronous `FULL` padding frames. It avoids
the accepted `wal2` checkpoint fullsync count matrix, WAL byte truncation,
checkpoint transaction, rollback-journal apply/commit, cache-spill `wal-11`,
`wal-18` checksum/page-size recovery, VFS writer/sync/lock, and pager4
DBMOVED batches.

Dependency closure:

No new support component is needed. The slice reuses the lane-local `SQLiteWal`
parser, transaction recovery boundary, checkpoint planning, and hydrated
upstream source truth.
