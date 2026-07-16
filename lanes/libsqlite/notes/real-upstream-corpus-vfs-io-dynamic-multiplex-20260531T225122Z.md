# Real Upstream Corpus VFS I/O Dynamic Multiplex Handoff

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T225122Z-0`

Base accepted HEAD: `292ada6b86cc431f7b1537075eacedfb4e905cf4`

## Scope

Added a source-neutral `SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile()` model for real upstream SQLite multiplex VFS behavior.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/multiplex.test`
  - `multiplex-1.0` / `multiplex-1.5` control API initialization and invalid controls
  - `multiplex-2.5` chunk-size and file-count behavior after large writes
  - `multiplex-2.7` disabled multiplex writes into one oversized base file
  - `multiplex-3.1` / `multiplex-3.2` multiple connection groups
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/multiplex2.test`
  - `multiplex2-1.1` through `multiplex2-1.3` multi-client read/delete/vacuum/reinsert visibility
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/multiplex3.test`
  - `multiplex3-1` faultsim checksum preservation
  - `multiplex3-2.1` through `multiplex3-2.100` 8.3 hot-journal copy recovery
  - `multiplex3-3` backup faultsim result/checksum behavior
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/multiplex4.test`
  - `multiplex4-1.1` chunk file creation
  - `multiplex4-1.2` truncate-enabled VACUUM chunk removal
  - `multiplex4-1.3` `PRAGMA multiplex_truncate`
  - `multiplex4-1.5` truncate-off VACUUM chunk preservation

The focused test adds 1,017 TestRunner PASS cases and 31,929 assertions over the multiplex scenarios above.

## Non-Overlap

This patch does not touch accepted VFS lock state, VFS file writer, rollback-journal apply/commit, sync plan/apply, delete-database sidecars, unix-excl locking, or mmap coverage. It adds a distinct multiplex VFS chunk/truncate/fault/hot-journal corpus profile.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMultiplexDynamic20260531Test.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMultiplexDynamic20260531Test.php` -> 1 test file, 31,929 assertions, 0 failures
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 1 test file, 3 assertions, 0 failures
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> lane-status json ok
- `git diff --check -- lanes/libsqlite` -> clean

## Dependency Closure

No new support component is required. The patch reuses the existing lane-local VFS I/O dynamic plan surface and adds bounded source-neutral multiplex VFS behavior derived from hydrated upstream SQLite Tcl tests.
