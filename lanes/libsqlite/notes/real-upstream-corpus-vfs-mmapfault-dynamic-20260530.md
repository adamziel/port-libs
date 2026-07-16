# real-upstream-corpus-vfs-mmapfault-dynamic-20260530

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T210037Z-0`

Base accepted HEAD: `c7f1da7bda346751170f57e7264f2081e65c2f0a`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapfault.test`
- Scenarios: `mmapfault 1-pre` and faultsim test `mmapfault 1`.

## Change

- Added `SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile()` for the upstream mmap fault contract: mmap enabled, cache size 5, UNIQUE indexes on `t1.a` and `t1.b`, a large transaction expanded from four saved rows, a fault during the final unique insert, connection reuse after the fault, allowed row-count outcomes of 5/65/66 after the recovery insert, final commit attempt, and preserved integrity.
- Added `SQLiteRealUpstreamCorpusVfsMmapFaultDynamicTest.php` with 1,000 distinct dynamic PASS cases plus focused citation and malformed-input guards.

## Non-overlap

This does not repeat accepted `io.test`, `ioerr.test`, `ioerr2.test`, `ioerr3.test`, `ioerr4.test`, `ioerr5.test`, `ioerr6.test`, `avfs.test`, WAL/SHM fault, VFS writer/sync/lock, rollback-journal apply, or pagerfault coverage. The owned upstream file is `mmapfault.test`, specifically the mmap-backed UNIQUE insert faultsim transaction.

## Dependency Closure

No new support component is required. The patch reuses the existing VFS dynamic corpus planning surface and adds a bounded native PHP mmap fault profile.
