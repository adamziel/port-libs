# real-upstream-corpus-vfs-io-dynamic-20260530T192504Z-0

Implemented a real upstream VFS I/O error dynamic corpus slice using hydrated
SQLite upstream files from `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream source files and scenarios:

- `ioerr.test`: `ioerr-1`, `ioerr-2`, `ioerr-4`, `ioerr-5`, `ioerr-7`,
  `ioerr-9`, `ioerr-10`, and `ioerr-12` I/O error recovery scenarios.
- `ioerr2.test`: nonpersistent pager retry scenario represented by
  `ioerr2-7`.
- `ioerr5.test`: persistent pager error-state and memory-reclaim dirty-page
  preservation scenario.

Coverage added:

- New focused test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php`.
- Adds 1,048 distinct TestRunner PASS cases over real upstream scenario names,
  VFS operations (`read`, `write`, `sync`, `truncate`, `delete`, `access`,
  `open`, `close`), and injected failpoints.
- Adds 14,580 focused behavior assertions covering expected SQLite I/O result
  classes, recovery actions, excluded failpoints, dirty-page preservation,
  stable database-image guarantees, dependency tags, and exact upstream
  scenario citation.

Non-overlap:

- Does not add metadata-only runner rows or fabricated `.test` names.
- Does not repeat accepted rollback-journal commit/apply, VFS sync/apply,
  locked writer, lock state, or atomic-write/default-page-size `io.test`
  coverage.
- This slice exercises the existing VFS I/O error injection behavior modeled
  by `SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome()`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php`
  passed: `1 test files, 14580 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded native
  PHP VFS I/O error outcome model and the hydrated upstream SQLite corpus as
  source truth.
