# real-upstream-corpus-vfs-sysfault-persistent-dynamic-20260531T054509Z

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T054509Z-0`

Implemented a non-overlapping real upstream VFS/IO dynamic syscall fault batch against the hydrated upstream source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/sysfault.test`
- `sysfault.test` `1`: persistent `open`/`getcwd` failures around opening, WAL switch, temp-table creation, and write body results.
- `sysfault.test` `1.2`: `fstat` `ENOMEM` and `EOVERFLOW` mapping, including large-file-support-disabled diagnostics.
- `sysfault.test` `1.3`: `fcntl` locking errno mapping for `unix` and `unix-excl` VFS paths.
- `sysfault.test` `3`: `fstat`/`fallocate` `EIO` during chunked writes with synchronous off.
- `sysfault.test` `4`: `mmap` `EACCES` during mapped reads.

Added `SQLiteVfsIoDynamicPlan::sysfaultPersistentUnixErrorProfile()` and `SQLiteRealUpstreamCorpusVfsSysfaultPersistentDynamicTest.php` with 1,000 distinct dynamic behavior cases plus source-citation and malformed-input guards.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSysfaultPersistentDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSysfaultPersistentDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSysfaultPersistentDynamicTest.php`
  - `1 test files, 28008 assertions, 0 failures`
  - Focused PASS growth: 1,003 TestRunner PASS lines, including 1,000 distinct real upstream behavior cases.

Non-overlap:

- This does not repeat accepted `sysfault.test` transient `EINTR` retry, `syscall.test` registry/open retry/peer-lock behavior, VFS lock state, locked writer, sync apply, rollback-journal apply, checksum reserve, quota, mmap warm/corrupt/sparse/pragma state, or existing `ioerr*` pointer-map and memory-reclaim rows.
- The owned upstream section is persistent Unix syscall errno mapping from `sysfault.test` scenarios `1`, `1.2`, `1.3`, `3`, and `4`.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local VFS/IO dynamic planner and adds bounded native PHP modeling for the upstream Unix syscall fault map.
