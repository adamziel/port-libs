# real-upstream-corpus-vfs-io-dynamic-remainder-20260531T004109Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T004109Z-0`

Base accepted HEAD: `ad16a572f80ccf85246d93f3ad58ce0402786c09`

## Upstream Sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - Scenarios: `io-6.1`, `io-6.2.1.*`, `io-6.2.2.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
  - Scenarios: `walvfs-4.*` through `walvfs-9.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapfault.test`
  - Scenario: `mmapfault-1`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmap2.test`
  - Scenario family: `mmap2-1.*` mmap/mremap syscall fault rows
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bigmmap.test`
  - Scenario families: sparse 1GiB boundary setup and mmap-size scan rows
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapwarm.test`
  - Scenario families: mmap warm success, misuse, and OOM boundaries

## Behavior Added

Added `lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicRemainderTest.php`
with 1,197 focused TestRunner cases and 19,615 assertions. The cases exercise
existing lane-local VFS/IO behavior models for:

- atomic-write pager-cache retention after corrupt-on-disk page probes;
- WAL SHM/read-mark readonly, busy, protocol, checkpoint-busy, and IOERR
  boundaries;
- mmap fault recovery after unique-index insert transactions;
- mmap/mremap `ENOMEM` fault logging and reusable connection state;
- sparse big-mmap table boundary reads and covering-index/correlated scans;
- mmap warm API result-code and page-warm boundaries.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicRemainderTest.php`
  - Result: `1 test files, 19615 assertions, 0 failures`
  - PASS lines: `1197`

## Non-Overlap

This batch does not repeat accepted `io.test` traffic/default-page-size
coverage, `journal2.test`, `ioerr.test` pointer-map coverage, WAL checkpoint or
savepoint byte truncation, VFS file writer/sync/lock-state/process-lock
clusters, mmap3 active-statement resize coverage, or app-WAL behavior. It owns
the remaining real upstream VFS/IO dynamic remainder listed above.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteVfsIoDynamicPlan` behavior surface and keeps all changes under
`lanes/libsqlite/**`.
