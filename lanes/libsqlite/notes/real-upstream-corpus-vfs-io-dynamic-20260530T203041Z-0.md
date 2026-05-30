# real-upstream-corpus-vfs-io-dynamic-20260530T203041Z-0

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/backup_ioerr.test`
- Covered scenario family: `backup_ioerr-2.*` through `backup_ioerr-13.*`,
  including transient and persistent I/O errors, destination page sizes 512,
  1024, and 4096, initially empty and pre-populated destination databases,
  partial backup-step failures, source-write failures that let backup continue,
  deferred backup-update failures, final backup-step failures, finish error
  publication, destination image restoration, contents-match success paths, and
  destination integrity checks.

Patch:

- Added `SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine()` to model the
  upstream backup-step/finish state machine without requiring ext/sqlite.
- Added `SQLiteRealUpstreamVfsBackupIoErrorStateMachineTest.php` with 1,008
  distinct upstream-backed state-machine cases plus malformed-input coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVfsBackupIoErrorStateMachineTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsBackupIoErrorStateMachineTest.php`: 1 test file, 18,364 assertions, 0 failures, 1,009 PASS lines.

Non-overlap:

- This does not repeat accepted `avfs.test`, `io.test`, `ioerr2.test`,
  `ioerr3.test`, `ioerr4.test`, `ioerr5.test`, `ioerr6.test`,
  `pagerfault.test`, `pagerfault2.test`, `pagerfault3.test`, `cksumvfs.test`,
  `walvfs.test`, VFS file writer, locked writer, lock-state, process-lock,
  sync-plan/apply, rollback-journal apply/commit, WAL checkpoint/savepoint, or
  prior backup reopen-fault coverage. The new surface is the specific
  `backup_ioerr.test` backup API step/update/finish error-state behavior.

Dependency closure:

- No new support component is required. The slice reuses existing bounded VFS
  dynamic fault-recovery plan infrastructure and adds a generic backup API
  state-machine planner.
