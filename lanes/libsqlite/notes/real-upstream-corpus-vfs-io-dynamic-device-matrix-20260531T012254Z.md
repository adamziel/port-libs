# real-upstream-corpus-vfs-io-dynamic-device-matrix-20260531T012254Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T012254Z-0`

Base accepted HEAD: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Ported sections: `io-2.4.1` through `io-2.11.2`, `io-3.1` through `io-3.3`, `io-4.1` through `io-4.3`, and `io-5`.

Behavior covered:

- Atomic write journal admission, deferred journal creation, blocked journal-path rollback, multi-file commit rollback, explicit rollback before journal creation, sector-size gating, atomic1k/atomic2k/atomic64k flags, and exclusive-locking journal suppression.
- Sequential VFS cache-spill sync suppression and commit-only database sync.
- Safe-append sync behavior, journal `nRec` sentinel handling, and one-header journal sizing across repeated cache spills.
- Default page-size choice from sector size and atomic device capabilities.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicDeviceMatrix20260531T012254ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicDeviceMatrix20260531T012254ZTest.php`
  - `1 test files, 59055 assertions, 0 failures`
  - `4004` focused PASS lines

Non-overlap:

This extends the real upstream VFS I/O dynamic corpus with `io.test` device-characteristic matrix sections. It does not repeat accepted VFS lock state, process locks, file writer, locked writer, rollback-journal apply/commit, sync plan/apply, WAL checkpoint transactions, savepoint rollback/byte truncation, mmap read-growth, appendvfs, temp-file lifecycle, file-control state, or the earlier quick-balance-only `io-1.*` coverage.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local `SQLiteVfsIoDynamicPlan` behavior helpers and the existing PHP test harness.
