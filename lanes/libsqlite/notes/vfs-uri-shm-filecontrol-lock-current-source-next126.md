# VFS URI SHM File-Control Lock Current Source Next126

## Scope

This isolated slice extends the existing VFS URI/SHM/file-control current-source planner with connection-aware SHM byte-lock ownership. It keeps the accepted next87/next92/next104 sidecar URI, file-control routing, and data-version generation behavior, then adds current-source SHM ownership evidence for multi-connection Application copy/import paths.

## Behavior

- `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext126()` records SHM lock owners by connection.
- Shared readers on the same SHM byte can coexist across connections.
- Exclusive SHM attempts report `busy` with the blocking connection when another connection owns the byte.
- Exclusive writer ownership blocks later shared readers until the writer unlocks.
- Closing the SHM handle releases both persisted lock modes and connection-owner state.
- Data-version reads through a stale SHM current source still route to the database handle and report the current owner generation.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlLockCurrentSourceNext126Test.php`
  - `1 test files, 52 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmFileControlLockCurrentSourceNext87Test.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlCurrentSourceNext92Test.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlCurrentSourceNext104Test.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlLockCurrentSourceNext126Test.php`
  - `4 test files, 230 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-vfs-uri-shm-lock-current-source-next126.php`
  - Prints valid JSON with `blockedExclusiveStatus: busy`, `blockedExclusiveOwners: ["wp-cron"]`, and stale SHM data-version detection.
- PHP lint passed for the changed source, test, and example.
- `git diff --check -- lanes/libsqlite` passed.

## Non-Overlap

This does not repeat accepted VFS lock byte ranges, VFS lock state, process file locks, locked writer application, next104 data-version generation behavior, rollback-journal apply, sync apply, or file writer/sync-plan clusters. It is a narrower xShmLock-style ownership/conflict behavior slice layered on the existing current-source machinery.

## Dependency Closure

No new support component is needed. The slice reuses the existing URI parser, current-source state model, and bounded SHM/file-control planner under `lanes/libsqlite/src`.
