# real-upstream-corpus-pager-wal-dynamic-20260531T075215Z-0

Added `SQLiteRealUpstreamPagerWalReadonlyRestartDynamic20260531T075215ZTest.php`.

Upstream source truth:
- `walro.test`: `walro-1.1.*` through `walro-1.4.*` read-only WAL/SHM reader behavior.
- `walro2.test`: read-only WAL snapshot/reopen boundaries.
- `walnoshm.test`: `walnoshm-1.*`, `2.1.*`, `2.2.*`, and `3.*` exclusive-lock WAL without xShm behavior.
- `walrestart.test`: `walrestart-1.2` and `1.4` checkpoint restart race behavior.
- `walpersist.test`: `walpersist-1.*` through `4.*` persistent-WAL close and journal-size-limit behavior.

Focused coverage:
- 1,000 distinct TestRunner behavior cases plus upstream citation and non-overlap/dependency cases.
- Each dynamic case validates WAL parse/checksum state, committed transaction boundaries, recovery boundary, reader snapshot page visibility, checkpoint mode result, durable checkpoint result, persistent-WAL close sidecar behavior, and read-only/exclusive-lock decision invariants.

Non-overlap:
- This avoids accepted warm-body, snapshot-boundary, auto-checkpoint, invalid page-size, hash-sidecar, lock-race, full-sync, rollback/savepoint, checkpoint-transaction, byte-truncation, VFS writer, rollback-journal commit/apply, and persist-mode-only pager/WAL batches.
- This slice owns read-only WAL reader, no-SHM exclusive-lock, restart-race, and persistent close/truncation cross-product behavior.

Dependency closure:
- No new support component is needed. The test reuses existing lane-local `SQLiteWal` parse, recovery-boundary, checkpoint, durable-checkpoint, reader-snapshot, and persistent-WAL close primitives.
