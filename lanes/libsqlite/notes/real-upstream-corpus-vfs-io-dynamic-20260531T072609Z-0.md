# real-upstream-corpus-vfs-io-dynamic-20260531T072609Z-0

Base accepted HEAD: `9d0b0fe07345f3693373fb79bddfe1aa2564a7a2`.

Added a focused real-upstream VFS I/O matrix in
`lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicSysfaultMatrixTest.php`.
The test ports additional hydrated upstream behavior from:

- `sysfault.test` section 1: persistent `open`/`getcwd` errors while opening and writing.
- `sysfault.test` section 1.2: `fstat` `ENOMEM`/`EOVERFLOW` while opening and writing.
- `sysfault.test` section 1.3: `fcntl` lock errno mapping on `unix` and `unix-excl`.
- `sysfault.test` section 2.1: transient `EINTR` retry across `open`, `ftruncate`, `close`, `read`, `pread`, `pread64`, `write`, and `fallocate`.
- `sysfault.test` section 3: `fstat`/`fallocate` `EIO` during chunked write paths.
- `sysfault.test` section 4: `mmap` `EACCES` during mapped reads.
- `syscall.test` section 4.2: `EINTR` open retry during attached commits in rollback and WAL modes.
- `syscall.test` section 5: sibling handle close must not drop peer process locks.
- `syscall.test` section 6.1-6.2: temp handle close and `temp_store=file` cleanup after spill.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicSysfaultMatrixTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicSysfaultMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicSysfaultMatrixTest.php`
  - `1 test files, 41514 assertions, 0 failures`
  - 2,566 focused PASS cases.

Non-overlap:

- This does not touch accepted VFS file writer, lock byte range, rollback journal apply/commit,
  sync plan/apply, WAL byte truncation, B-tree page move/freeblock/overflow, JSON table cursor/source/constraint,
  SELECT SQL text/group/order/subquery, or Unicode GLOB clusters.
- It extends VFS I/O dynamic corpus coverage with additional `sysfault.test` and `syscall.test`
  matrix combinations rather than adding generated metadata rows.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded
  `SQLiteVfsIoDynamicPlan` support for real upstream Unix VFS syscall/fault behavior.
