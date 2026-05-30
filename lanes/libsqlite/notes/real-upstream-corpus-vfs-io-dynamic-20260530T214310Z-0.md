## real-upstream-corpus-vfs-io-dynamic-20260530T214310Z-0

Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`.

Added a focused real upstream VFS syscall-fault cluster from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/sysfault.test`.

Covered upstream sections:

- `sysfault.test` `sysfault-1`: open/getcwd failures during WAL open/write.
- `sysfault.test` `sysfault-1.2`: fstat `ENOMEM` and `EOVERFLOW` open/write failures.
- `sysfault.test` `sysfault-1.3`: fcntl lock errno mapping for `unix`/`unix-excl`.
- `sysfault.test` `sysfault-2.1`: transient `EINTR` retry across open, ftruncate, close, read, pread, pread64, write, and fallocate.
- `sysfault.test` `sysfault-2.2`: persistent syscall faults during attached commit.
- `sysfault.test` `sysfault-3`: fstat/fallocate faults during large insert.
- `sysfault.test` `sysfault-4`: mmap `EACCES` read fault.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSysfaultDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSysfaultDynamicTest.php` passed: `1 test files, 16205 assertions, 0 failures`, with 1531 focused PASS lines.

Non-overlap:

This extends the VFS I/O dynamic corpus with `sysfault.test` syscall fault
behavior and avoids the already accepted mmap read, ioerr2/3/4, pointer-map
fault, backup I/O, temp lifecycle, file-control, lock matrix, walvfs, VFS
writer, lock-state, process-lock, sync, and rollback-journal application
clusters.

Dependency closure:

No new support component is needed. The slice reuses the existing
`SQLiteVfsIoTrafficPlan` VFS fault modeling surface and records the dependency
as generic SQLite VFS syscall fault simulation.
