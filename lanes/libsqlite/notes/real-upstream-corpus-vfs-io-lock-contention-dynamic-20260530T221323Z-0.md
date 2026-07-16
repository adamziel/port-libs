## real-upstream-corpus-vfs-io-lock-contention-dynamic-20260530T221323Z-0

Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`.

Added a focused real upstream VFS lock-contention cluster from the hydrated
SQLite upstream checkout at
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Covered upstream files and sections:

- `lock.test` `lock-1`, `lock-2`, `lock-3`, `lock-4`, `lock-5`, `lock-6`,
  and `lock-7`: shared/reserved/pending/exclusive main database transitions.
- `lock2.test` `lock2-1`: pending writer locks block new readers while
  preserving existing reader state.
- `lock3.test` `lock3-1` through `lock3-4`: `BEGIN`, `BEGIN DEFERRED`,
  `BEGIN IMMEDIATE`, and `BEGIN EXCLUSIVE` acquisition behavior.
- `lock5.test` dotfile/flock/none sections: platform VFS lock-style
  differences.
- `lock7.test` `lock7-1`: TEMP database lock status alongside main database
  locks.

Behavior added:

- `SQLiteTransactionBeginLockPlan::upstreamLockContentionProfile()` models a
  two-connection lock sequence with rollback/WAL journal modes, VFS locking
  styles, initial lock state, reader/writer contention, commit blocking,
  busy-result reporting, lock sequence rows, and upstream provenance.
- `SQLiteRealUpstreamCorpusVfsLockContentionDynamicTest.php` ports 972
  distinct lock-contention scenario/journal/round combinations plus validation
  and provenance checks.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteTransactionBeginLockPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLockContentionDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLockContentionDynamicTest.php` passed: `1 test files, 12645 assertions, 0 failures`, with 975 focused PASS lines.

Non-overlap:

This covers real upstream `lock*.test` VFS lock-contention behavior. It avoids
the already accepted `sysfault.test`, `ioerr*.test`, mmap, backup I/O,
walvfs, file-control, WAL/SHM, VFS writer, lock-state, process-lock, sync,
rollback-journal application, and previous `BEGIN` lock-mode parser coverage.

Dependency closure:

No new support component is needed. The slice reuses the existing generic
transaction lock planning surface and adds a bounded native PHP upstream
lock-contention profile. No domain-specific libsqlite API is introduced.
