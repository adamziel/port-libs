# real-upstream-corpus-pager-wal-dynamic-20260530T173845Z-0

Implemented a current-base pager/WAL real upstream corpus slice from hydrated
SQLite upstream `test/wal.test`.

Covered upstream scenarios:

- `wal.test` `wal-1.0` through `wal-1.5`: WAL-mode create/read/write warm-body
  transaction behavior.
- `wal.test` `wal-2.1` through `wal-2.6`: MVCC reader snapshots while a writer
  appends commits.
- `wal.test` `wal-3.1` through `wal-3.3`: transaction rollback ignores
  uncommitted WAL tail frames.
- `wal.test` `wal-4.1` through `wal-4.5`: savepoint rollback truncates WAL
  prefixes and preserves committed outer changes.

Focused PHP test:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalWarmBodyDynamicTest.php`

Dependency closure: no new support component is required. This reuses the
existing native PHP WAL parser, checkpoint, reader snapshot, transaction
recovery, and savepoint stack primitives.

Non-overlap: this does not repeat the accepted `wal2.test` header recovery,
`walmode.test`, `walpersist.test`, readonly checkpoint, lock race, byte
truncation, rollback-journal apply, or VFS writer clusters. It ports the
upstream `wal.test` warm-body behavior surface.
