# Real Upstream Corpus VFS IO Dynamic 20260530T221830Z

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash3.test`
- Scenarios: `io-2.3` through `io-2.11`, `crash3-1`, `crash3-2`, and `crash3-3`.

## Change

- Added `SQLiteVfsIoTrafficPlan::atomicWriteJournalDecision()` for the upstream
  `io.test` atomic-write optimization: journal absence, deferred journal
  creation at commit, sector-size/page-size gating, specific IOCAP_ATOMIC1K/2K
  flags, exclusive locking, second-connection visibility before commit, and
  rollback on journal-open failure.
- Added `SQLiteVfsIoTrafficPlan::crashRecoveryDeviceProfile()` for upstream
  `crash3.test` VFS device-characteristic crash recovery: atomic journal sync
  absence, database versus journal crash boundaries, sequential and safe-append
  integrity preservation, and the sequential+atomic corner case.
- Added `SQLiteRealUpstreamCorpusVfsAtomicCrashDynamicTest.php` with 1,912
  distinct focused TestRunner PASS cases and 21,351 behavior assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicCrashDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicCrashDynamicTest.php` passed: `1 test files, 21351 assertions, 0 failures`, with 1,912 focused PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

## Non-Overlap

This extends the VFS I/O real corpus with atomic-write device-characteristics
and crash-recovery behavior from `io.test` and `crash3.test`. It avoids the
already accepted mmap read, ioerr2/3/4/5/6, pointer-map fault, syscall fault,
backup I/O, temp lifecycle, file-control, lock matrix, walvfs, VFS writer,
sync/apply, rollback-journal apply/commit, and WAL checkpoint/savepoint
clusters.

## Dependency Closure

No new support component is required. The patch reuses the existing generic
`SQLiteVfsIoTrafficPlan` VFS traffic modeling surface and adds bounded native
PHP behavior for upstream atomic-write and crash-recovery device
characteristics.
