# real-upstream-corpus-pager-wal-dynamic-20260531T010820Z-0

Base accepted HEAD: `714d8628d70df34f443545659c4afed0ff8c4b1b`.

This slice adds `SQLiteRealUpstreamPagerWalLateWal2DynamicTest.php`, a focused
late `wal2.test` continuation against the hydrated upstream SQLite file at
`/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`.

Covered upstream sections:

- `wal2.test` `wal2-10.1.4`: newer WAL header version refuses recovery.
- `wal2.test` `wal2-10.2.3`: newer wal-index version refuses read/write.
- `wal2.test` `wal2-11.2`: malformed hash table rejected on write.
- `wal2.test` `wal2-11.3`: malformed hash table rejected on read.
- `wal2.test` `wal2-12.2.*`: WAL and SHM inherit database permissions.
- `wal2.test` `wal2-13.*`: database/WAL/SHM open and readonly permission matrix.
- `wal2.test` `wal2-14.*`: `checkpoint_fullfsync` controls WAL and database
  fullsync counts.

Focused movement:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLateWal2DynamicTest.php`
- Result: `1 test files, 27999 assertions, 0 failures`, with `1001` focused
  PASS lines.
- Expected selected `phpPass` movement if accepted: `1429270 -> 1430271`.
- Mapped denominator movement: none; mapped coverage remains complete at
  `1589 / 1589`.

Non-overlap:

This does not repeat the accepted pager/WAL hook, noop checkpoint, exclusive
mode, overwrite, recovery, restart-overwrite, crash, MVCC, WAL checkpoint
transaction, VFS rollback/savepoint/commit, or app-WAL parked slices. It targets
late `wal2.test` protocol and filesystem-response sections that were not in
the current accepted pager/WAL dynamic corpus.

Dependency closure:

No new support component is needed. The slice reuses the existing bounded native
`SQLiteWal` parser, recovery-boundary, checkpoint, reader-snapshot, durable
checkpoint, and persistent-WAL close helpers.
