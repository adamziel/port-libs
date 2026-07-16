# VFS SHM Lock File-Control Current Source Next85

## Delta

- Added `SQLiteVfsShmLockFileControlCurrentSource::currentSourceNext85()` for WAL-mode VFS coordination where xFileControl writes require an exclusive SHM write/checkpoint/recover lock and a fresh current-source generation.
- Added focused tests for writer, readonly reader, stale-source blocking, read-lock conflict, checkpoint-lock writes, validation, and no-lock blockers.
- Added a Application smoke for copied `wp_options` WAL-mode opens that rehydrate persisted controls only after SHM-locked current-source updates.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmLockFileControlCurrentSourceNext85Test.php`
- Expected focused delta: 47 PASS lines in one new focused test file.
- Example smoke: `php lanes/libsqlite/examples/application-vfs-shm-lock-filecontrol-current-source-next85.php`

## Non-Overlap

This does not repeat accepted VFS file writer, VFS lock-state/process-lock, VFS file-control current-source next82, WAL byte truncation, WAL checkpoint transaction, or SHM read-mark recovery work. The new behavior is the cross-gate between SHM lock ownership, stale current-source generation checks, and xFileControl persistence.

## Dependency Closure

No new support component is required. The slice reuses lane-local VFS/SHM concepts and records a bounded native PHP coordination primitive for future pager/VFS transaction application.
