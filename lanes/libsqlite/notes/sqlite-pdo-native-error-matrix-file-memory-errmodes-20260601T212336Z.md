# SQLitePDO native error matrix: file, memory, errmodes

Micro-slice: `sqlite-pdo-native-error-matrix-file-memory-errmodes-20260601T212336Z`

## Behavior

This slice closes a bounded PDO-native parity gap across both `sqlite::memory:`
and file-backed `SQLitePDO` connections:

- `PDO::ERRMODE_SILENT`, `PDO::ERRMODE_WARNING`, and
  `PDO::ERRMODE_EXCEPTION` now share focused matrix coverage for connection
  prepare failures and statement execute parameter failures.
- Warning-mode messages use native-style `PDO::prepare()` and
  `PDOStatement::execute()` labels, matching local `pdo_sqlite` warning text.
- `SQLitePDOStatement::setFetchMode()` now uses a PHP 8.2-compatible `true`
  return type, so warning capture no longer sees an unrelated return-type
  deprecation while auditing PDO warnings.
- Statement-level execute failures remain statement-scoped and do not clobber
  connection `PDO::errorInfo()`; later statement success clears statement
  error state and persists file-backed rows.

The new focused test adds six real TestRunner PASS cases and 238 behavior
assertions: file/memory crossed with silent/warning/exception modes. Native
`pdo_sqlite` is used only as a local oracle when available.

## Reproduction

Before the fix, a local native-oracle probe showed `ERRMODE_WARNING` emitted
polyfill labels like `PortLibs\LibSqlite\SQLitePDO::prepare()` instead of
native `PDO::prepare()`, and warning capture could also collect a
`SQLitePDOStatement::setFetchMode()` return-type deprecation.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeErrorMatrixFileMemoryErrmodesTest.php`
  - Passed: `1 test files, 238 assertions, 0 failures`.
  - PASS cases: memory silent, memory warning, memory exception, file silent,
    file warning, file exception.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorMatrixFileMemoryErrmodesTest.php`
  - Passed: `8 test files, 1073 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-native-error-matrix.php --self-test`
  - Passed: `application-pdo-native-error-matrix self-test passed`.
- `php -l lanes/libsqlite/src/SQLitePDO.php && php -l lanes/libsqlite/src/SQLitePDOStatement.php && php -l lanes/libsqlite/tests/SQLitePdoNativeErrorMatrixFileMemoryErrmodesTest.php && php -l lanes/libsqlite/examples/application-pdo-native-error-matrix.php`
  - Passed: no syntax errors detected in all changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Passed: `1 test files, 8 assertions, 0 failures`.
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Passed: `lane-status json ok`.
- `git diff --check -- lanes/libsqlite`
  - Passed with no whitespace errors.

## Non-overlap

This does not repeat accepted PDO invalid-column, invalid GROUP BY, invalid
INSERT, invalid DML, file persistence, or silent false-return coverage. It owns
the file/memory plus errmode matrix and the warning-message/deprecation parity
needed to make that matrix native-comparable.

## Dependency Closure

No new support component is needed. This reuses `SQLitePDO`,
`SQLitePDOStatement`, `SQLitePdoFileImage`, existing generic application
settings fixtures, and local native `pdo_sqlite` only as a focused oracle.
