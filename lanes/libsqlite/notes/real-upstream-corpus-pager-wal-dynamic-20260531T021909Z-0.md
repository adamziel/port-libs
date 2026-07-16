# real-upstream-corpus-pager-wal-dynamic-20260531T021909Z-0

Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`
- Ported section: `walro.test` `2.1.1` through `2.1.4`.

Behavior covered:

- A readonly `readonly_shm=1` connection can open while a writer checkpoint is
  inside the checkpoint `xSync` hook.
- The readonly reader keeps using the WAL snapshot rowset during checkpoint
  backfill and sees the pre-checkpoint `t2` count of 4 rows.
- After checkpoint sync is complete, readonly readers use the checkpointed
  database rowset.
- Missing database/WAL/SHM sidecars or non-readonly SHM admission block the
  readonly checkpoint snapshot.
- Readonly checkpoint clients still cannot drive `PRAGMA wal_checkpoint`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteWalReadonlyShmPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointReadonlyDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointReadonlyDynamicTest.php`
  - `1 test files, 14923 assertions, 0 failures`
  - `1003` focused PASS lines

Non-overlap:

This extends readonly WAL coverage past the accepted `walro.test` `1.*` and
`walro2.test` cache-refresh/page-size matrix into the later concurrent
checkpoint hook path. It avoids WAL byte truncation, rollback commit/apply, VFS
writer/sync/lock state, checkpoint transaction, readonly-SHM open-only, and
generic pager recovery duplicates.

Dependency closure:

No new support component is needed. The patch reuses the generic readonly
WAL/SHM planning surface and the hydrated upstream `walro.test` source file.
