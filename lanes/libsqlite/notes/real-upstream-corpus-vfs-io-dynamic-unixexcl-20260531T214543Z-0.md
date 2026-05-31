# Real Upstream Corpus VFS I/O Dynamic Unix-Excl

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T214543Z`
Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T214543Z-0`
Base accepted HEAD: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/unixexcl.test`
- Ported scenarios:
  - `unixexcl-1.*`: read/write `-vfs unix-excl` opens take a process-wide exclusive lock on first read; same-process peers can still access, external processes see `database is locked`.
  - `unixexcl-2.*`: read-only `-vfs unix-excl` opens behave like the ordinary unix VFS; external readers can still read.
  - `unixexcl-3.*`: WAL database opened through `file:test.db?psow=0 -vfs unix-excl`; external readers are blocked, same-process readers keep a snapshot until commit, and checkpoints report complete frame counts.

## Patch Scope

- Added `SQLiteVfsIoDynamicPlan::unixExclVfsProfile()` with bounded source-neutral modeling for unix-excl peer contexts, read-only behavior, lock scope, WAL URI parameters, reader snapshot visibility, and checkpoint frame counts.
- Added `SQLiteRealUpstreamCorpusVfsIoUnixExclDynamic20260531Test.php` with 1002 generated upstream-backed behavior cases plus citation and malformed-input guards.
- Updated `lane-status.json` from `3847998` to `3849002` selected PHP PASS cases. Mapped coverage remains `1589 / 1589`.

## Non-Overlap

This handoff owns only upstream `unixexcl.test` unix-excl process-wide VFS lock and WAL same-process snapshot behavior. It avoids accepted VFS file writer, sync plan/apply, lock-state, process-lock, rollback-journal apply/commit, super-journal, WAL checkpoint/savepoint byte-truncation, existing `exclusive.test` / `exclusive2.test`, `nolock`, `win32nolock`, `lock4`, `lock7`, `superlock`, `sharedlock`, `shmlock`, `appendvfs`, `cksumvfs`, `mmap`, `delete_db`, `8_3_names`, `journal1`, `journal2`, and `journal3` coverage.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoUnixExclDynamic20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoUnixExclDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoUnixExclDynamic20260531Test.php`
  - `1 test files, 30403 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoUnixExclDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsExclusiveLockingDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `3 test files, 55392 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded `SQLiteVfsIoDynamicPlan` real-corpus VFS/I/O dynamic model and adds source-neutral unix-excl behavior to it.
