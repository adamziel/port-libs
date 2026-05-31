# real-upstream-corpus-vfs-io-dynamic-20260531T025336Z-0

Base accepted HEAD: `892244279ab2272eec684ce3477ab002d81ab0b4`.

Ported real upstream SQLite VFS syscall coverage from hydrated
`/home/claude/port-libs/.upstream-cache/libsqlite/test/syscall.test`:

- `syscall.test` 1.1.1-1.3.2: unix VFS `xSetSystemCall` reset/install and
  `SQLITE_NOTFOUND` handling.
- `syscall.test` 2.1.1-2.1.2: `xGetSystemCall` existence checks.
- `syscall.test` 3.1: `xNextSystemCall` enabled-call iteration.
- `syscall.test` 7.1-7.3: single-byte database file opens as empty while
  two-or-more bytes are rejected as not-a-database.
- `syscall.test` 8.1-8.2.5: chunk-size `xFileControl` size-hint rounding.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallIoDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallIoDynamicTest.php` passed: `1 test files, 227 assertions, 0 failures`.

Non-overlap:

- This avoids accepted VFS file writer, locked writer, sync, rollback-journal
  apply, lock-state/process-lock, WAL byte-truncation, mmap, walvfs, and
  io.test traffic clusters. It adds syscall.test registry/open/size-hint
  behavior under the existing generic `SQLiteVfsIoDynamicPlan`.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded VFS
  dynamic plan surface and the local PHP test runner.
