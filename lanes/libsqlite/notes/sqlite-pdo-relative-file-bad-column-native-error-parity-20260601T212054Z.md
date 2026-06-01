# SQLitePDO relative-file bad-column native error parity

Micro-slice: `sqlite-pdo-relative-file-bad-column-native-error-parity-20260601T212054Z`

## Behavior

File-backed `SQLitePDO` now resolves relative SQLite filenames at open time,
matching native `pdo_sqlite` connection lifetime behavior when the process
changes directories after the connection is opened.

The focused path opens `sqlite:relative.sqlite`, creates and seeds
`app_settings`, changes into a different directory, hits a prepare-time
missing-column error, and then performs a valid write. Native PDO keeps writing
to the originally opened file; `SQLitePDO` now does the same instead of creating
a stray `relative.sqlite` in the later working directory.

The missing-column error remains native-style:

- `SELECT key_name FROM app_settings WHERE missing_key = 1`
  fails during `prepare()`.
- Error info is `['HY000', 1, 'no such column: missing_key']`.
- No statement is created for the failed prepare.
- The original file is unchanged by the failed prepare.
- A later successful write clears the connection error state and persists to
  the originally opened relative file.

## Evidence

Red-first probe before the fix:

`poly bad=["HY000",1,"no such column: missing_key"] poly rows=["alpha"] secondExists=yes`

`native bad=["HY000",1,"no such column: missing_key"] native rows=["alpha","beta"] secondExists=no`

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePDO.php`
  passed with no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php`
  passed with no syntax errors.
- `php -l lanes/libsqlite/examples/application-pdo-relative-file-bad-column.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php`
  passed with `1 test files, 23 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php lanes/libsqlite/tests/SQLitePdoRelativeFileBadColumnNativeParityTest.php`
  passed with `8 test files, 858 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-relative-file-bad-column.php --self-test`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 8 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  passed.
- `git diff --check -- lanes/libsqlite`
  passed with no output.

## Non-overlap

This does not repeat accepted invalid target-column, invalid scalar DML,
missing-table identifier, silent false-return, absolute file-backed invalid
column, grouped SELECT bad-column, surplus-parameter, or file-image
preservation coverage. It owns only the relative file path anchoring behavior
that affects native error parity and subsequent writes after a bad-column
prepare failure.

Expected focused PASS movement is `+1` from the new focused TestRunner case.
Mapped upstream coverage remains unchanged.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePDO`,
`SQLitePDOStatement`, `SQLitePdoFileImage`, generic application settings
fixtures, and local native `pdo_sqlite` only as a focused parity oracle in
tests.
