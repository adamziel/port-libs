# Real upstream corpus VFS IO dynamic follow-up

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T171930Z-0`

Accepted base: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test`
  - `avfs-3.1` through `avfs-3.5`: appendvfs growth, reopen integrity, delete/vacuum shrink, and prefix preservation.
  - `avfs-5.1` through `avfs-5.2`: refuse too-tiny SQLite databases appended to empty or prefixed files.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-4.2.2` and `io-4.3.4`: safe-append journal header `nRec` and no extra journal headers after cache spills.
  - `io-5`: default page-size selection from sector size and atomic device capability flags.

## Ported behavior

- Extended `SQLiteVfsIoDynamicPlan` with appendvfs growth/shrink integrity modeling, tiny appended database refusal, default page-size choice, and safe-append journal sizing.
- Extended `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` with 7 additional focused TestRunner PASS cases and 1813 additional assertions.
- Focused VFS dynamic file now passes `23` PASS cases and `2894` assertions.

Non-overlap: this avoids accepted VFS file writer, locked writer, lock-state, process locks, rollback-journal apply/commit, sync plan/apply, file-control/nolock, atomic-write visibility, quick-balance, and prior avfs append-offset clusters. The new surface is upstream appendvfs growth/tiny-open refusal plus `io.test` safe-append sizing and default page-size selection.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 2894 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses existing native PHP VFS/open/file-control primitives.
