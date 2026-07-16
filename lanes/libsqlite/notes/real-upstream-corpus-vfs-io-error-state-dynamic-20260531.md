# Real Upstream Corpus VFS I/O Error-State Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T045029Z-0`

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr6.test`

Ported behavior cluster:

- `ioerr5-1`: persistent I/O error during commit leaves the pager in error state while a read cursor is open; later UTF-16 statement compilation and memory pressure must not spill dirty pages into the database image.
- `ioerr5-2`: `sqlite3_release_memory()` against a pager in error state must preserve dirty pages until the transaction is resolved.
- `ioerr6-1`: first atomic-device write returning `SQLITE_FULL` rolls back the statement and leaves `PRAGMA integrity_check` ok.
- `ioerr6-2`: atomic-device full faults during primary-key table setup preserve integrity.
- `ioerr6-3`: atomic-device full faults during schema setup still allow a follow-up schema change and preserve integrity.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTransactionSequencePlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorStateDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorStateDynamicTest.php` passed: `1 test files, 894 assertions, 0 failures`, with `78` PASS lines.

Non-overlap:

- This does not repeat accepted VFS file writer, locked writer, sync plan/apply, rollback-journal apply/commit, super-journal commit, WAL checkpoint transactions, WAL byte truncation, tempfault, mmap, or `io.test` atomic/default-page-size sequence coverage.
- The new behavior is specifically pager error-state memory reclamation and atomic `SQLITE_FULL` recovery from `ioerr5.test` and `ioerr6.test`.

Dependency closure:

- No new support component is required. The batch extends the existing generic VFS I/O transaction-sequence planner and reuses the existing TestRunner/autoload path.

Expected dashboard movement:

- `phpPass`: `2125874 -> 2125952` (`+78` focused PASS lines).
- `phpFail`: unchanged at `0`.
- Mapped coverage: unchanged at `1589 / 1589`.
