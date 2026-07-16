# real-upstream-corpus-pager-wal-dynamic-20260531T013916Z-0

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T013916Z-0`

Accepted base: `d0e37b664c0ef9500748faeafd4d7f1484470255`

## Upstream source truth

Hydrated checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test`

Ported upstream files and sections:

- `walrestart.test`: restart checkpoint race after complete backfill.
- `walckptnoop.test`: noop checkpoint observes frames without backfill.
- `walcrash.test`: recover committed WAL prefix after crash tail.
- `walcrash2.test`: recover database after large uncheckpointed WAL append.
- `walcrash3.test`: journal_size_limit truncate fault keeps prior rows visible.
- `walfault.test`: checkpoint and WAL recovery fault boundaries.

## Focused coverage

Added `SQLiteRealUpstreamPagerWalRestartCheckpointDynamicTest.php` with 1,000
dynamic restart/checkpoint/crash-tail rows plus one upstream provenance row.
The dynamic matrix covers page sizes 512/1024/2048/4096, big/little-endian WAL
checksums, clean committed restart frames, uncommitted valid tails, corrupt
tails, truncated tails, no-commit WAL files, noop/passive/full/restart/truncate
checkpoint modes, and bounded reader end-frame behavior.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRestartCheckpointDynamicTest.php`
- Result: `1 test files, 21201 assertions, 0 failures`
- Focused PASS-line growth: `+1001`

## Non-overlap

This extends the pager/WAL real upstream corpus without repeating the accepted
`wal2-15.*` batch, `walckptnoop`/`waloverwrite`/`walpersist` metadata-only
coverage, WAL savepoint byte truncation, rollback-journal commit/super-journal
application, VFS writer/sync/lock/apply paths, pager master-journal numbered
surfaces, or app-WAL parity.

## Dependency closure

No new support component is needed. The test reuses the existing native
`SQLiteWal` recovery, checkpoint, durable checkpoint, reader visibility, and
checksum primitives against real upstream WAL restart/checkpoint/crash-tail
behavior.
