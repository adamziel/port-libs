# SQLitePDO native error persistence parity

Micro-slice: `sqlite-pdo-native-error-persistence-parity-20260601T1710Z-0`

## Behavior

This slice aligns pure-PHP `SQLitePDO` error state with observed native
`pdo_sqlite` behavior for prepared-statement execution failures:

- Connection-level prepare failures now attach native-style
  `PDOException::$errorInfo` alongside `PDO::errorInfo()`.
- Successful `prepare()` clears the connection error state while the new
  statement starts with native initial statement error state.
- `PDOStatement::execute()` surplus-parameter failures stay scoped to the
  statement, keep the connection at `['00000', null, null]`, and persist on
  the statement across later successful connection queries.
- A later successful execute of the same statement clears the statement error
  state.
- File-backed databases remain byte-identical after the failed statement
  execute and can accept later successful writes.

## Evidence

Red before source change:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php`

Failed with `1 test files, 4 assertions, 2 failures` because
`PDOException::$errorInfo` was `NULL` and statement execution errors clobbered
the connection error state.

Passing focused verification:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php`

Passed with `1 test files, 29 assertions, 0 failures`.

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php`

Passed with `3 test files, 533 assertions, 0 failures`.

`php lanes/libsqlite/examples/application-pdo-error-persistence.php --self-test`

Passed with `application-pdo-error-persistence self-test passed`.

## Non-overlap

This does not repeat accepted invalid target-column, unknown scalar-column,
missing-table, or file rollback parity. It covers the narrower native
statement-versus-connection error-state lifetime and exception `errorInfo`
surface for prepared-statement execution failures.

## Dependency Closure

No new support component is needed. The patch reuses the existing pure-PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, and local native
`pdo_sqlite` only as the focused test oracle.
