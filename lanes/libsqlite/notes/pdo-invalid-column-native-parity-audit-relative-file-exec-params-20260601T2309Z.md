# SQLitePDO relative-file invalid-column exec-parameter audit

Micro-slice: `pdo-invalid-column-native-parity-audit-relative-file-exec-params-20260601T2309Z`

## Behavior

This slice extends the file-backed `SQLitePDO` native-parity audit for relative
database filenames:

- A connection opened with a relative `sqlite:relative.sqlite` DSN remains
  anchored to the originally resolved file after the process changes CWD.
- A parameterized `exec($sql, $params)` invalid target-column INSERT reports
  native-style `HY000/1` connection error info before mutation and does not
  create a stray file in the new CWD.
- A later parameterized successful INSERT with an omitted placeholder value
  still writes to the originally opened file, binding the omitted value as
  `NULL`, and the reopened relative file shows the same rows as native
  prepared-statement behavior.

The application smoke remains source-neutral and uses generic `app_settings`
rows.

## Evidence

Pre-edit focused checks on the current base were already green:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php
1 test files, 23 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php
1 test files, 47 assertions, 0 failures

php lanes/libsqlite/examples/application-pdo-relative-file-bad-column.php --self-test
application-pdo-relative-file-bad-column self-test passed
```

The patch adds a combined relative-file plus exec-parameter regression to the
existing focused PDO audit and updates the matching application smoke. Focused
PASS movement is `+1` TestRunner case in
`SQLitePdoRelativeFileBadColumnNativeParityTest.php`; mapped upstream coverage
is unchanged.

Post-edit focused verification:

```text
php -l lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php

php -l lanes/libsqlite/examples/application-pdo-relative-file-bad-column.php
No syntax errors detected in lanes/libsqlite/examples/application-pdo-relative-file-bad-column.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php
2 PASS cases; 1 test files, 48 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php
13 PASS cases; 4 test files, 402 assertions, 0 failures

php lanes/libsqlite/examples/application-pdo-relative-file-bad-column.php --self-test
application-pdo-relative-file-bad-column self-test passed

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 9 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePdoDdlDmlErrorStateNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorMatrixFileMemoryErrmodesTest.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php
45 PASS cases; 10 test files, 1428 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```

## Non-overlap

This does not repeat the accepted exact misspelled `namedd` INSERT snippet,
absolute file-backed invalid INSERT audit, conflict-modifier DML audit, omitted
prepared INSERT parameter binding, invalid GROUP BY parity, or non-parameterized
relative-file bad-column test. It owns only the combined relative-file,
CWD-change, invalid target-column `exec($sql, $params)`, and later
parameterized write behavior.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePDO`,
`SQLitePDOStatement`, `SQLitePdoFileImage`, generic application settings
fixtures, and local native `pdo_sqlite` only as a focused parity oracle.
