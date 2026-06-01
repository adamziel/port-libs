# SQLitePDO invalid INSERT exec-parameter native audit

Micro-slice: `pdo-native-invalid-insert-file-persistence-exec-params-audit-20260601T182033Z`

## Behavior

This slice tightens `SQLitePDO` parameter-array behavior against native
`pdo_sqlite` for file-backed INSERT statements:

- Omitted positional or named execute-array placeholders now bind as `NULL`
  instead of failing with a synthetic missing-parameter error.
- Surplus positional or named execute-array entries still fail with native
  `column index out of range` error info before any row mutation.
- Invalid numbered parameters such as `?0` still fail with native
  `variable number must be between ?1 and ?32766`.
- Parameterized invalid INSERT paths through the polyfill's `exec($sql,
  $params)` helper preserve the file-backed database image and expose
  connection-scoped error info.
- Prepared-statement surplus execute failures remain statement-scoped and keep
  the connection at `['00000', null, null]`.

The focused audit uses native `PDO` as an oracle for omitted prepared INSERT
parameters and sparse surplus execute arrays, and uses generic `app_settings`
rows for the polyfill-only `exec($sql, $params)` helper.

## Evidence

- Red-first audit:
  native `PDOStatement::execute(['x'])` on `INSERT INTO t(a,b) VALUES (?,?)`
  inserted `['x', null]`, while `SQLitePDO` threw
  `SQLitePDO missing positional parameter ?`.
- `php -l lanes/libsqlite/src/SQLitePDO.php`
- `php -l lanes/libsqlite/src/SQLitePDOStatement.php`
- `php -l lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
- `php -l lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php`
- `php -l lanes/libsqlite/examples/application-pdo-invalid-insert-exec-params-audit.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php`
  passed with `1 test files, 41 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php`
  passed with `4 test files, 628 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `6 test files, 644 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
  passed with `1 test files, 49 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-error-persistence.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-pdo-silent-error-parity.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-pdo-invalid-insert-exec-params-audit.php --self-test`
  passed.

## Non-overlap

This does not repeat accepted missing-table, invalid-column, silent false-return,
or statement error-persistence coverage. It owns the native omitted-parameter
INSERT binding correction and the file-backed invalid INSERT audit for the
polyfill's parameterized `exec()` helper.

Expected focused assertion movement is `+37`: the new file adds `41`
assertions, while the existing polyfill test drops four assertions that encoded
the old non-native missing-parameter error expectation.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePDO`,
`SQLitePDOStatement`, `SQLitePdoFileImage`, generic application settings test
fixtures, and local native `pdo_sqlite` only as a focused parity oracle.
