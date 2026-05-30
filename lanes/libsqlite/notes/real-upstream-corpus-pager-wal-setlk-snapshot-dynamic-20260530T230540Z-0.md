# real-upstream-corpus-pager-wal-dynamic-20260530T230540Z-0

Added `SQLiteRealUpstreamPagerWalSetlkSnapshotDynamicTest.php` as an additive real upstream pager/WAL corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk_snapshot.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test`

Ported behavior cluster:

- `walsetlk.test` 1.0-1.8: forced WAL recovery while writer/checkpoint locks are active.
- `walsetlk.test` 2.*: blocking-lock `BEGIN EXCLUSIVE` and `PRAGMA wal_checkpoint=RESTART` waits.
- `walsetlk_snapshot.test` 1.1-1.5: snapshot-open receives busy while a checkpoint writer is active, then the connection continues on the latest snapshot.
- `walprotocol.test` 2.5-2.8: recovery lock-release boundary that allows a concurrent reader retry.

Focused coverage:

- 250 generated upstream-shaped WAL variants.
- 1,000 distinct TestRunner PASS cases plus one upstream citation case.
- Each variant builds parseable WAL bytes and checks native `SQLiteWal` snapshot visibility, checkpoint busy/reset behavior, read-mark pinning, and native `SQLiteVfsLockState` byte-range lock conflict/retry behavior.

Non-overlap:

- This does not repeat accepted pager/WAL protocol retry, read-mark matrix, persist/noop/checksum, crash recovery, checkpoint sync, WAL transaction recovery, or snapshot-boundary batches. The new surface is the `walsetlk`/`walsetlk_snapshot` blocking-lock and snapshot-open interaction, expressed through native WAL frame parsing, checkpoint decisions, read-mark planning, and byte-range lock state.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP `SQLiteWal`, `SQLiteWalHeader`, `SQLiteLockByteRangePlan`, and `SQLiteVfsLockState`.

Dashboard note:

- Mapped denominator remains unchanged because the upstream manifest is already complete. This is countable focused PHP PASS-line growth from real hydrated upstream scenarios.
