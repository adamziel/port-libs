# Real upstream corpus: pager/WAL restart and noop dynamic

Added `SQLiteRealUpstreamPagerWalRestartNoopDynamicCorpusTest.php` as an additive real-upstream pager/WAL corpus batch.

Source truth from the hydrated SQLite checkout:

- `walrestart.test`: restart checkpoint over a fully checkpointed WAL before later writer reuse.
- `wal6.test` sections 4.2 through 4.4: partially checkpointed WAL files keep later frames visible to readers.
- `walckptnoop.test` sections 1.1 through 1.10: noop checkpoint does not backfill or reset WAL sidecars.
- `wal.test` sections `wal-1.*` through `wal-4.*`: transaction recovery preserves a valid committed prefix before an invalid or uncommitted WAL tail.

Focused growth: 1001 distinct TestRunner cases. The generated cases vary page size, page count, checkpoint sequence, checksum byte order, reader frame, salts, and frame layout while exercising native `SQLiteWal` parsing, checksum validation, checkpoint mode/result planning, durable restart/noop behavior, reader visibility, and transaction recovery boundaries.

Non-overlap: this does not repeat accepted pager WAL mode/persist cases, WAL sync matrix, WAL overwrite/restart, savepoint rollback, rollback-journal apply, VFS file writer/sync/lock clusters, B-tree, JSON, PRAGMA, SELECT, or source-neutral cleanup. It focuses on restart/noop/partial-checkpoint reader boundaries from distinct real upstream scripts.

Dependency closure: no new support component is needed; this reuses the existing native WAL parser, checkpoint planner, durable checkpoint result, reader visibility, and transaction recovery helpers.
