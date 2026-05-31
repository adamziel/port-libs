# Real upstream corpus VFS I/O dynamic file-control persistence

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T070306Z`

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T070306Z-0`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filectrl.test`
- Ported sections:
  - `filectrl-1.1`: main database file-control probe.
  - `filectrl-1.2`: temp schema opened before file-control probe.
  - `filectrl-1.3`: in-memory handle probe.
  - `filectrl-1.4`: last-error file-control probe.
  - `filectrl-1.5`: lockproxy file-control probe.
  - `filectrl-1.6`: generated `etilqs_` temp filename probe.

## Patch

Added `SQLiteRealUpstreamVfsFileControlPersistenceDynamic20260531Test.php`
with 1,000 dynamic file-control persistence cases plus 2 source/count guards.
The cases exercise generic SQLite VFS file-control behavior through existing
native PHP helpers:

- persistent `persist_wal`, `reserve_bytes`, and `powersafe_overwrite` state;
- close/reopen reload of persistent controls;
- ignored file-control calls while the handle is closed;
- `name_hint`, `tempfile`, `mmap_size`, `chunk_size`, `busy_timeout`, and
  `data_version` result behavior;
- generic file URI parsing and varying sector/sync/cache inputs.

## Non-overlap

This does not repeat accepted WAL VFS SHM/readmark/checkpoint batches,
appendvfs growth/update/content batches, VFS writer/locked-writer/sync/lock
state/process-lock/rollback-journal/super-journal work, `io.test` atomic and
device matrices, `ioerr*`/pagerfault recovery matrices, mmap/syscall/temp
fault clusters, or JSON/B-tree/SQL executor behavior. The owned upstream
surface is specifically `filectrl.test` file-control callback and tempfilename
behavior, extended through persistent generic file-control state.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsFileControlPersistenceDynamic20260531Test.php`
  - `1 test files, 24004 assertions, 0 failures`
  - `1002` focused TestRunner PASS cases

## Dependency closure

No new support component is required. The slice reuses existing bounded native
components: `SQLiteVfsFileControlPersistencePlan`, `SQLiteVfsFileControlState`,
`SQLiteVfsCapabilityPlan`, and `SQLiteVfsIoDynamicPlan`.
