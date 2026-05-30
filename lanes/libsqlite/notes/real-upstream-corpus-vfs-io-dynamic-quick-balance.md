# real-upstream-corpus-vfs-io-dynamic-quick-balance

Status: focused real-upstream VFS I/O behavior growth for `real-upstream-corpus-vfs-io-dynamic-20260530T164904Z-0`.

Behavior:
- Ports the `io.test` `io-1.1` through `io-1.5` write-traffic expectations into `SQLiteVfsIoTrafficPlan::quickBalanceInsertTraffic()`.
- Covers schema root creation, root-leaf fill writes, the first split into two leaves, subsequent existing-leaf inserts, and the quick-balance insert that adds a third leaf with only three database page writes.
- Keeps the slice generic: no domain-specific API, no generated fake upstream script ids, and no new denominator row claim.

Focused verification:
- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`
  - `1 test files, 114 assertions, 0 failures`

Expected dashboard movement: `phpPass +14` focused PASS lines from the new `io-1.*` VFS I/O dynamic corpus cases. Mapped coverage remains `958 / 1589`; this is additional focused PHP behavior over already mapped `io.test` inventory.

Non-overlap: avoids accepted VFS file writer, locked writer, lock state, process locks, rollback-journal apply/commit, sync plan/apply, file-control/nolock, atomic-write, sequential, safe-append, WAL VFS, and I/O error boundary clusters. The new surface is upstream `io.test` quick-balance database-write accounting before the existing `io-2.*` atomic-write block.

Dependency closure: no new support component is needed; this reuses the lane-local VFS I/O traffic planner.
