# VFS SHM URI File-Control Lock Current Source Next131

## Scope

This slice extends the current VFS URI/SHM/file-control current-source planner with SQLite-style multi-slot `xShmLock` range behavior. It builds on accepted next126 connection-owned single-slot SHM locks and adds atomic range acquisition/unlock evidence for read-mark and checkpoint/recover/write byte ranges.

## Behavior

- `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext131()` accepts `span`/`n`/`count` on SHM lock operations.
- A SHM range applies to ordered lock slots (`checkpoint`, `recover`, `write`, `read0` through `read4`) without partially mutating state when any covered slot conflicts.
- Shared overlapping ranges merge connection owners per slot.
- Exclusive range attempts report `busy`, the exact blocking lock slots, and blocking connection names.
- Range unlock clears only the requesting connection's owned slots, preserving unrelated slots.
- URI owner decoding, file-control routing, readonly/nolock guards, and stale `data_version` detection continue to use the current-source machinery.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteVfsShmUriFileControlLockCurrentSourceNext131Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-vfs-shm-uri-lock-range-current-source-next131.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmUriFileControlLockCurrentSourceNext131Test.php`
  - `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-vfs-shm-uri-lock-range-current-source-next131.php`
  - Prints JSON with `blockedStatus: busy`, `blockedLocks: ["read2"]`, `exclusiveAfterUnlock: ok`, `staleDataVersion: true`, and `finalLockCount: 3`.

## Non-Overlap

This does not repeat accepted VFS lock byte ranges, VFS lock state, process file locks, locked writer application, next126 single-slot SHM owner tracking, next128 URI file-control reads, rollback-journal apply, sync apply, or file writer/sync-plan clusters. The new behavior is multi-slot SHM range atomicity and per-slot conflict reporting.

## Dependency Closure

No new support component is needed. The slice reuses the existing URI parser and current-source VFS/SHM/file-control planner.
