# SQLitePDO native false-return error parity

Micro-slice: `sqlite-pdo-native-error-parity-false-success-audit-20260601T175100Z`

## Behavior

This slice audits and fixes the remaining PDO native error-mode path where a
failed SQLite operation must return strict `false`, not report success or throw
under `PDO::ERRMODE_SILENT`.

- `SQLitePDO` now accepts native SQLite PDO error modes:
  `ERRMODE_EXCEPTION`, `ERRMODE_SILENT`, and `ERRMODE_WARNING`.
- In silent mode, connection-level `prepare()`, `query()`, and `exec()`
  failures return `false`, preserve native-style `PDO::errorInfo()`, and leave
  the file-backed image unchanged.
- Successful connection work after a silent connection-level failure clears the
  connection error state like native `pdo_sqlite`.
- In silent mode, `SQLitePDOStatement::execute()` failures return `false`,
  keep the error scoped to the statement, leave the connection at
  `['00000', null, null]`, and preserve the statement error across later
  successful connection queries until that statement succeeds.

The application smoke uses generic `app_settings` rows and verifies missing
table, invalid target column, and surplus statement parameters as strict false
returns before a later successful write.

## Evidence

Red before implementation:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php`

Failed with `1 test files, 0 assertions, 2 failures` because
`SQLitePDO` rejected `PDO::ERRMODE_SILENT`.

Passing focused verification:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php`

Passed with `1 test files, 65 assertions, 0 failures`.

Adjacent PDO regression gate:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php`

Passed with `4 test files, 600 assertions, 0 failures`.

Application smoke:

`php lanes/libsqlite/examples/application-pdo-silent-error-parity.php --self-test`

Passed with `application-pdo-silent-error-parity self-test passed`.

## Non-overlap

This does not repeat the accepted exception-mode invalid identifier,
invalid-column, surplus-parameter, file-persistence, or statement error
persistence patches. It owns only the native `ERRMODE_SILENT` false-return
surface and the small attribute-mode support needed to reach that behavior.

## Dependency Closure

No new support component is needed. The patch reuses `SQLitePDO`,
`SQLitePDOStatement`, `SQLitePdoFileImage`, and the local native `pdo_sqlite`
driver only as an oracle inside focused tests.
