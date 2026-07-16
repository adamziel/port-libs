# real-upstream-corpus-vfs-io-dynamic-20260531T043732Z-0

Implemented a non-overlapping real upstream VFS I/O dynamic traffic batch against the hydrated upstream source file `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.

Owned upstream sections:

- `io.test` `io-2.*`: atomic-write optimization, journal fallback for multi-page/appending/multifile transactions.
- `io.test` `io-3.*`: `IOCAP_SEQUENTIAL` rollback-journal sync reduction.
- `io.test` `io-4.*`: `IOCAP_SAFE_APPEND` rollback-journal header sync reduction.
- `io.test` `io-5.*`: default page-size selection from device characteristics.
- `io.test` `io-6.*`: dirty cache-spill sync behavior after atomic commit path.

Focused PHP coverage:

- Added `SQLiteRealUpstreamCorpusVfsIoDynamicTrafficBatchTest.php`.
- The file owns exactly 1,000 dynamic TestRunner cases plus two source/ownership guard cases.
- Each dynamic case exercises the existing native `SQLiteVfsIoTrafficPlan::transaction()` behavior over device flags, page sizes, changed pages, append pages, sync modes, dirty spill, directory sync, and multifile commit flags.
- Non-overlap: this does not repeat accepted VFS lock state, locked writer, sync apply, rollback-journal apply, checksum VFS, `ioerr*`, `cksumvfs`, or existing traffic-matrix rows. It extends the real `io.test` traffic family with a larger dynamic matrix.

Dependency closure:

- No new support component is needed. The slice reuses the existing native bounded VFS I/O traffic planner and upstream `io.test` semantics.
