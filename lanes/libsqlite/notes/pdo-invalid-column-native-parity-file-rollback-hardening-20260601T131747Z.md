# SQLitePDO invalid scalar column rollback hardening

Micro-slice: `pdo-invalid-column-native-parity-file-rollback-hardening-20260601T131747Z`

## Behavior

File-backed `SQLitePDO` now treats unresolved bare identifiers in INSERT and
UPDATE value expressions as native SQLite column-resolution errors:

- `INSERT INTO test (name) VALUES (missing_column)` reports
  `no such column: missing_column`.
- `INSERT INTO test (name) VALUES ('queued'), (missing_column)` aborts the
  whole statement, leaves the original row set intact, and leaves the
  persisted SQLite file bytes unchanged.
- `UPDATE test SET name = missing_column WHERE id = 1` reports
  `no such column: missing_column` without mutating the in-memory row set or
  persisted file image.

The focused test uses local native `pdo_sqlite` as an oracle when available and
still enforces the same raw `errorInfo()[2]` message and rollback invariants
against the pure-PHP implementation.

## Evidence

- Red before source change:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  failed with `1 test files, 143 assertions, 1 failures` because the polyfill
  reported `SQLitePDO unsupported scalar expression: missing_column`.
- Passing focused behavior:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  passed with `1 test files, 204 assertions, 0 failures`.

## Non-overlap

This extends the prior invalid target-column file-persistence parity slice by
covering unresolved value-expression identifiers and multi-tuple statement
rollback. It does not repeat JSONB CHECK cleanup, SELECT unknown-column
normalization, VFS rollback-journal application, or broader release/all
runner evidence.

## Dependency Closure

No new support component is needed. The patch reuses the existing pure-PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, and `SQLiteSelectSql`
paths plus optional local native `pdo_sqlite` only as a test oracle.
