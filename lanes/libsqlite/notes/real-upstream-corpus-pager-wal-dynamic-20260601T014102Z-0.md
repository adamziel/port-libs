# real-upstream-corpus-pager-wal-dynamic-20260601T014102Z-0

Status: ready for integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
- Upstream sections: `wal-12.1` through `wal-12.6`, the reused WAL prefix regression where a shorter new WAL must ignore stale frames left from an older longer WAL.

Implementation:

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReusedLogPrefixRows()` with 1000 dynamic rows over the two `wal-12.*` phases.
- Added `SQLiteRealUpstreamCorpusPagerWalReusedPrefixDynamic20260601Test`, which builds valid new WAL prefixes, appends stale old-salt frame tails, and verifies checksum recovery, transaction recovery, checkpoint image application, and stale-tail rejection through the native `SQLiteWal` parser.

Focused evidence:

- New focused TestRunner cases: 1003.
- Behavior assertions: 34015 from the focused test file.
- Non-overlap: this targets `wal.test` `wal-12.*` stale reused-log tail recovery. It avoids accepted `wal-17` full-sync padding, `wal-18` checksum/page-size recovery, `wal-19` close checkpoint, `wal-16` attached checkpoint, `wal-11` cache spill, rollback-journal apply/commit, VFS writer/sync/lock, and WAL savepoint byte truncation clusters.

Dependency closure:

- No new support component needed. The slice reuses `SQLiteWal` checksum recovery, transaction recovery, checkpoint image application, and hydrated upstream `wal.test` evidence.
