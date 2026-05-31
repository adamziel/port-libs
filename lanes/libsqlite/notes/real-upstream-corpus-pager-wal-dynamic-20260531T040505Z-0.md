# real-upstream-corpus-pager-wal-dynamic-20260531T040505Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T040505Z`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowallock.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk_recover.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walseh1.test`

Behavior ported:

- `rowallock.test` `1.*`: read-only WAL clients keep a stable read view, reject writes, and leave the WAL sidecar available after another writer appends.
- `walsetlk3.test` `1.1`, `1.2`, `2.2`, and `2.3`: blocking and nonblocking connect behavior while close/checkpoint or rollback-mode exclusive locks are active.
- `walsetlk_recover.test` `1.2` through `1.5`: recovery readers time out behind a delayed WAL read, then continue successfully after the blocking reader exits.
- `walseh1.test` `1` through `6`: system-error-handler fault injection preserves WAL read, write, rollback, checkpoint, and later insert behavior.
- Adds 1,000 distinct dynamic TestRunner cases over these real upstream sections, plus source-citation and provenance rows.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRecoveryFaultDynamicTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRecoveryFaultDynamicTest.php`
  - Result: `1 test files, 20017 assertions, 0 failures`
  - Focused PASS growth: `+1002` TestRunner cases from real upstream pager/WAL scripts.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

Non-overlap:

- This covers native PHP behavior for upstream scripts that previously appeared only in runner-map evidence in this worktree.
- It avoids accepted WAL checkpoint/fullsync, WAL protocol, WAL setlk base/snapshot, walnoshm, WAL byte truncation, VFS writer/sync/lock/rollback, rollback-journal commit/apply, pager master-journal numbered surfaces, and existing pagerfault/journal VFS I/O clusters.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is countable PHP PASS-line growth over already mapped real upstream WAL inventory.

Dependency closure:

- No new support component is needed. This reuses the existing bounded pager/WAL dynamic plan surface and models process-lock, timeout, read-only, and fault-injection outcomes as lane-local native PHP behavior.
