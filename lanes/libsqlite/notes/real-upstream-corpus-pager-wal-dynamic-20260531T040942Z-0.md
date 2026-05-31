# real-upstream-corpus-pager-wal-dynamic-20260531T040942Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T040942Z`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal64k.test`
  - `wal64k` `1.0` through `1.3`: 64KiB OS page-size WAL/SHM growth and integrity.
  - `wal64k` `2.1`: unix-excl WAL database with 512-byte pages and large row population.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal7.test`
  - `wal7-1.0` through `wal7-1.2`: no `journal_size_limit` keeps a large WAL after checkpoint.
  - `wal7-2.0`, `wal7-3.0`, and `wal7-4.0`: size-limit and zero-limit WAL sidecar truncation boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal8.test`
  - `wal8` `1.0` through `3.1`: empty or stale first connection page-size handling after a peer initializes WAL mode.

## Patch

- Added `SQLiteRealUpstreamPagerWalDynamic20260531T040942ZTest.php`.
- The test builds bounded native WAL byte streams and database images inside each case, then exercises:
  - `SQLiteWal::parse()` checksum validation, including 64KiB and 512-byte WAL page-size cases.
  - `SQLiteWal::transactionRecoveryBoundary()` committed transaction boundaries.
  - `SQLiteWal::checkpointDatabaseImage()` durable page image application.
  - `SQLiteWal::checkpointModePlan()` and `durableCheckpointResult()` for passive, noop, restart, and truncate modes.
  - `SQLiteWal::readerSnapshotPageImage()` current and midpoint reader visibility.

## Focused evidence

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T040942ZTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T040942ZTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T040942ZTest.php
1 test files, 15609 assertions, 0 failures
```

- Focused PASS growth: `+1201` TestRunner PASS cases.
- Focused behavior assertions: `15609`.
- Expected `phpPass` movement: `2006296 -> 2007497`.
- Mapped denominator movement: none; this is already-mapped real upstream pager/WAL behavior.

## Non-overlap

This avoids the accepted `wal2-15.*` xSync matrix, `walckptnoop`, `waloverwrite`, `walpersist`, `walhook`, WAL savepoint byte truncation, VFS writer/sync/lock/rollback clusters, rollback-journal commit/super-journal work, pager master-journal reader-cache surfaces, and previous `pager1` real-pager boundary batches. The owned behavior is real upstream `wal64k`, `wal7`, and `wal8` WAL page-size/sidecar-limit/stale-connection behavior through native WAL parsing, checkpoint, and reader visibility helpers.

## Dependency closure

No new support component is needed. The slice reuses existing lane-local WAL parser/checkpoint/recovery and reader snapshot helpers.
