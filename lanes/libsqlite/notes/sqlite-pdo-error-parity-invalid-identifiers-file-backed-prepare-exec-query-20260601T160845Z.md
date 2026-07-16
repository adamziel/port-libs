# SQLitePDO invalid identifier file-backed error parity

Micro-slice: `sqlite-pdo-error-parity-invalid-identifiers-file-backed-prepare-exec-query-20260601T160845Z`

## Behavior

File-backed `SQLitePDO` now normalizes missing table identifier diagnostics to
match native `pdo_sqlite` raw `errorInfo()[2]` messages for the supported
prepare, exec, and query paths:

- `exec()` invalid `INSERT`, `UPDATE`, and `DELETE` targets report
  `no such table: missing_table`.
- `query()` invalid single-table and joined `SELECT` sources report
  `no such table: missing_table`.
- `prepare()` invalid `INSERT`, `UPDATE`, `DELETE`, single-table `SELECT`,
  and joined `SELECT` sources fail during prepare like native PDO and do not
  create a statement.

The focused file-backed test keeps a byte hash of the persisted SQLite image
and proves each rejected operation leaves the file unchanged across reopen.

## Evidence

- Red-first:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  failed because direct `exec()` surfaced
  `SQLitePDO table missing_table does not exist` instead of
  `no such table: missing_table`.
- Passing focused behavior:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  passed with `1 test files, 495 assertions, 0 failures`.
- Direct PDO/source-neutral gate:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `3 test files, 511 assertions, 0 failures`.

## Non-overlap

This owns only SQLitePDO missing table identifier parity for file-backed
`prepare()`, `exec()`, and `query()` paths. It does not repeat accepted invalid
column, execute-parameter, file persistence, source-neutral cleanup,
JSON/WAL/VFS/B-tree corpus, PRAGMA, trigger, row-value, or upstream-runner
coverage.

## Dependency Closure

No new support component is needed. The slice reuses the existing pure-PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, `SQLiteSelectSql`, and
optional local native `pdo_sqlite` oracle used by the focused tests.
