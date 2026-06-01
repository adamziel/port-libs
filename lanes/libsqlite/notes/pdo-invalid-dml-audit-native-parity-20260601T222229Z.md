# SQLitePDO invalid DML conflict-modifier native parity

Micro-slice: `pdo-invalid-dml-audit-native-parity-20260601T222229Z`

## Behavior

This slice tightens file-backed `SQLitePDO` native parity for invalid DML that
uses SQLite conflict modifiers:

- `INSERT OR IGNORE`, `INSERT OR REPLACE`, `INSERT OR FAIL`, and `REPLACE INTO`
  now route through the existing INSERT `VALUES` parser, so invalid target
  columns and invalid scalar expressions fail with native `pdo_sqlite` error
  info before mutation.
- `UPDATE OR IGNORE`, `UPDATE OR FAIL`, and `UPDATE OR REPLACE` now route
  through the existing UPDATE validator/executor, so invalid assignment columns
  and scalar expressions fail at the same prepare/exec phase as native PDO.
- Anonymous UPDATE parameters now preserve SQL-order numbering across the
  `SET` list and `WHERE` predicate, so `UPDATE ... SET value = ? WHERE key = ?`
  binds the second execute value in the predicate like SQLite.
- File-backed images remain unchanged after rejected conflict-modifier DML and
  reopen with the original rows.

The application smoke stays source-neutral with generic `app_settings` rows and
verifies an invalid `INSERT OR IGNORE` plus a valid parameterized
`UPDATE OR REPLACE`.

## Evidence

Red-first focused test before the source change:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS SQLitePDO invalid UPDATE and DELETE DML match native prepare exec and parameter error parity
FAIL SQLitePDO INSERT and UPDATE conflict modifiers keep native invalid DML parity (lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php)
prepare insert or ignore invalid target column phase
Expected: 'prepare'
Actual: 'execute'

1 test files, 149 assertions, 1 failures
```

Passing focused verification after the source change:

- `php -l lanes/libsqlite/src/SQLitePDO.php`
- `php -l lanes/libsqlite/src/SQLiteInsertValuesSql.php`
- `php -l lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php`
- `php -l lanes/libsqlite/examples/application-pdo-invalid-dml-native-parity-audit.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php`
  passed with `1 test files, 273 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePdoDdlDmlErrorStateNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorMatrixFileMemoryErrmodesTest.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php`
  passed with `10 test files, 1390 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
  passed with `2 test files, 60 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-invalid-dml-native-parity-audit.php --self-test`
  passed.

## Non-Overlap

This does not repeat the accepted misspelled INSERT-column user snippet,
relative-file bad-column parity, DDL error-state parity, invalid GROUP BY
parity, omitted INSERT parameter binding, direct invalid UPDATE/DELETE scalar
expression checks, or surplus prepared/exec parameter checks. It owns only the
conflict-modifier DML parser/validator surface and the directly exposed
anonymous UPDATE `SET`/`WHERE` parameter-order parity gap.

Expected focused PASS movement is `+1`: the existing audit file gains one new
TestRunner case, expanding from 148 to 273 assertions. Mapped upstream coverage
is unchanged.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLiteInsertValuesSql`, `SQLitePdoFileImage`,
and local native `pdo_sqlite` only as the parity oracle in focused tests.
