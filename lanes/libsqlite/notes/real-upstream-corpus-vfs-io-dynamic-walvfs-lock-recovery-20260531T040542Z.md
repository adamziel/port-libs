# real-upstream-corpus-vfs-io-dynamic-walvfs-lock-recovery-20260531T040542Z

## Scope

- Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T040542Z-0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
  - `walvfs-6.0` through `walvfs-6.2`: WAL restart reports locking protocol when a shared readmark lock cannot be reacquired.
  - `walvfs-7.0` through `walvfs-7.1`: checkpoint returns the busy tuple when the checkpointer lock is unavailable.
  - `walvfs-8.0` through `walvfs-8.3`: version-2 VFS checkpoint flushes a stale page cache before subsequent reads.
  - `walvfs-9.0` through `walvfs-9.1`: readonly SHM map/init plus lock I/O error surfaces as disk I/O error.

## Behavior Added

- Added `SQLiteVfsIoDynamicPlan::walVfsLockRecoveryProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsWalvfsLockRecoveryDynamicTest.php` with 600 focused dynamic TestRunner cases over the four upstream WAL VFS lock/recovery sections.
- This does not repeat accepted `walvfs` sections 1/3/5, WAL journal-size-limit/interruption/readmark coverage, VFS file writer/sync/lock/process-lock work, rollback-journal apply/commit, checkpoint transaction previews, JSON/SQL/B-tree behavior, or metadata-only runner admission.

## Evidence

- Focused verification is recorded in the worker final response.

## Dependency Closure

- No new support component is needed.
- Reuses the existing lane-local bounded `SQLiteVfsIoDynamicPlan` VFS/WAL I/O profile surface.
