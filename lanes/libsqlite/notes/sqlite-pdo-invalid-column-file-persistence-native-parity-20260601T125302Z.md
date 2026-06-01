# SQLitePDO invalid-column file persistence native parity

Micro-slice: `sqlite-pdo-invalid-column-file-persistence-native-parity-20260601T125302Z`

## Behavior

File-backed `SQLitePDO` now normalizes invalid-column diagnostics to match the
native `pdo_sqlite` raw `errorInfo()[2]` messages for this supported subset:

- `INSERT INTO test (namedd) ...` reports `table test has no column named namedd`.
- `UPDATE test SET namedd = ...` reports `no such column: namedd`.
- `SELECT namedd FROM test` reports `no such column: namedd`.
- `SELECT name FROM test WHERE namedd = 1` reports `no such column: namedd`.

The focused test also proves each rejected operation leaves the persisted
SQLite file image byte-for-byte unchanged and that reopening the file still
returns the original row.

## Source Truth

Local PHP `pdo_sqlite` is used as the oracle when available. The test returns
early for native comparison only when `PDO::getAvailableDrivers()` does not
include `sqlite`, but it still enforces the expected SQLite diagnostics and
file-image preservation against the pure-PHP implementation.

## Evidence

- Red before source change:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  failed because INSERT included the `SQLitePDO` prefix and UPDATE still used
  the INSERT-style `has no column named` diagnostic.
- Passing focused behavior:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  passed `1 test files, 166 assertions, 0 failures`.
- Guard and handoff checks:
  `php -l lanes/libsqlite/src/SQLitePDO.php`,
  `php -l lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`,
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`,
  `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR);'`,
  and `git diff --check -- lanes/libsqlite` all passed.

## Dependency Closure

No new support component is needed. The slice reuses the existing pure-PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, and `SQLiteSelectSql`
paths plus optional local native `pdo_sqlite` only as an oracle in tests.
