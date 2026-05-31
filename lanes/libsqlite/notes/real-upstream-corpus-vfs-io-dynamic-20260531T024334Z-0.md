## real-upstream-corpus-vfs-io-dynamic-20260531T024334Z-0

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`.

Added a real upstream VFS I/O lock-status cluster from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/lock7.test`.

Covered upstream sections:

- `lock7.test` `lock7-1.1`: both connections start transactions.
- `lock7.test` `lock7-1.2` and `lock7-1.3`: reading schema lock status leaves
  `main` unlocked and `temp` closed.
- `lock7.test` `lock7-1.4`: the first writer upgrades to a RESERVED main lock.
- `lock7.test` `lock7-1.5`: the second writer is blocked.
- `lock7.test` `lock7-1.6` and `lock7-1.7`: lock status is `main reserved`
  for the writer and `main unlocked` for the blocked second connection.
- `lock7.test` `lock7-1.8`: commit releases the RESERVED lock.

Focused movement:

- New PHP TestRunner file:
  `SQLiteRealUpstreamCorpusVfsLock7SchemaReadDynamicTest.php`.
- Focused PASS cases: `1003`.
- Focused behavior assertions: `12379`.
- Expected lane `phpPass` movement: `1726669 -> 1727672`.
- Mapped denominator movement: none; mapped coverage was already complete at
  `1589 / 1589`.

Non-overlap:

This batch extends the existing VFS lock corpus with the specific `lock7.test`
schema-read lock-status invariant. It does not repeat VFS process locks,
lock byte ranges, lock-state application, nolock URI suppression, JSON table
source/cursor work, VFS writer/sync/rollback application, WAL checkpoint or
savepoint byte work, quota/quota2 lifecycle, sysfault, ioerr5, or the broad
lock-contention matrix.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLock7SchemaReadDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLock7SchemaReadDynamicTest.php`
  - `1 test files, 12379 assertions, 0 failures`

Dependency closure:

No new support component is needed. The slice reuses the lane-local generic VFS
I/O dynamic planner and adds bounded schema-read lock-status modeling.
