# real-upstream-corpus-vfs-mmap-read-dynamic-20260530T211110Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T211110Z-0`
Base accepted HEAD: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmap1.test`
  - `mmap1-1.1` through `mmap1-1.6`: mapped-reader xRead count differences, peer truncate/grow visibility, integrity preservation, and stale mapping safety.
  - `mmap1-6.0` through `mmap1-6.7`: mapped file truncation through `VACUUM` after deleting a large row.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmap2.test`
  - `mmap2-1.mmap.1` through `mmap2-1.mmap.19`: ENOMEM injection through `mmap()`, row-count preservation, integrity, and log emission.
  - `mmap2-1.mremap.1` through `mmap2-1.mremap.19`: ENOMEM injection through `mremap()`, row-count preservation, integrity, and conditional log emission.

## Implementation

- Extended `SQLiteVfsIoDynamicPlan` with:
  - `mmapReadGrowthProfile()` for `mmap1.test` mapped read-count, peer truncate, peer grow, and integrity behavior.
  - `mmapVacuumTruncationProfile()` for `mmap1.test` mapped file truncation after `VACUUM`.
  - `mmapSyscallFailureProfile()` for `mmap2.test` `mmap`/`mremap` ENOMEM fault logging and recovery.
- Added `SQLiteRealUpstreamCorpusVfsMmapReadDynamicTest.php` with 1,042 focused TestRunner PASS cases and 25,685 assertions.

## Non-Overlap

This does not repeat accepted appendvfs growth/tiny-open behavior, safe-append/default-page-size `io.test` behavior, `mmapfault.test` unique insert fault recovery, `ioerr2` through `ioerr6`, late `ioerr.test` pointer-map coverage, checksum/WAL VFS coverage, WAL SHM fault coverage, VFS file writer, lock-state, sync-plan/apply, rollback-journal apply/commit, or WAL checkpoint/savepoint clusters.

The owned upstream surface is specifically `mmap1.test` mapped reader growth/truncation and `mmap2.test` mmap/mremap syscall ENOMEM fault behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapReadDynamicTest.php`
  - `1 test files, 25685 assertions, 0 failures`
  - `1042` selected PASS lines

## Dependency Closure

No new support component is required. The patch reuses the existing VFS I/O dynamic planning surface and adds bounded native PHP helpers for upstream mmap VFS behavior.
