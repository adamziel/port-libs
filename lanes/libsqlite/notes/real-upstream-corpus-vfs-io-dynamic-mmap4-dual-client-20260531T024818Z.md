# real-upstream-corpus-vfs-io-dynamic-mmap4-dual-client-20260531T024818Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T024818Z-0`

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmap4.test`
- Scenarios: `mmap4` cases 1 through 11, each with 100 alternating dual-client iterations.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile()` for the `mmap4.test` two-connection matrix.
- The model records both PRAGMA `mmap_size` values, alternating writer/reader ownership, mmap vs fallback read path, peer `count(*)`/checksum/integrity result shape, remap requirement, and reuse after remap.

Focused coverage:

- Added `SQLiteRealUpstreamCorpusVfsMmap4DualClientDynamicTest.php`.
- Focused test result: `1 test files, 29711 assertions, 0 failures`.
- PASS-line delta: `+1102` distinct TestRunner cases.

Non-overlap:

- This owns only `mmap4.test` dual-client mmap-size/remap behavior.
- It avoids accepted `mmap1.test` read-growth/vacuum truncation, `mmap2.test` syscall failures, `mmap3.test` active-statement resize, `mmapfault.test`, `bigmmap.test`, `mmapwarm.test`, `mmapcorrupt.test`, `io.test`, `ioerr*.test`, `avfs.test`, `cksumvfs.test`, `walvfs.test`, quota, lock, VFS writer/sync/rollback-journal, and pager/WAL checkpoint clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmap4DualClientDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmap4DualClientDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The slice reuses the existing native VFS I/O dynamic planner surface and adds a bounded mmap remap profile for hydrated upstream `mmap4.test`.
