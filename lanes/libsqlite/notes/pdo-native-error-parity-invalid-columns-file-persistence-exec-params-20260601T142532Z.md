# SQLitePDO execute-parameter native parity

Micro-slice: `pdo-native-error-parity-invalid-columns-file-persistence-exec-params-20260601T142532Z`

## Behavior

`SQLitePDO` now matches native `pdo_sqlite` for the supported execute-parameter
edge cases covered by the local oracle:

- Prepared DML accepts numbered qmark parameters such as `?2, ?1`.
- Mixed explicit and anonymous qmarks advance the anonymous cursor after the
  explicit index, so `?2, ?` consumes parameters 2 and 3 like SQLite.
- The polyfill's parameterized `exec($sql, $params)` path uses the same DML
  binding behavior.
- Surplus positional or named execute-array entries fail with
  `column index out of range` before any row mutation is written.
- Invalid `?0` parameters fail with
  `variable number must be between ?1 and ?32766`.

The file-backed regression keeps a byte hash of the persisted SQLite image
after successful numbered inserts and proves all rejected parameter errors leave
that image unchanged across reopen.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePDO.php`
- `php -l lanes/libsqlite/src/SQLitePDOStatement.php`
- `php -l lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  passed with `1 test files, 352 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `3 test files, 367 assertions, 0 failures`.

## Non-overlap

This extends the existing invalid-column/file-persistence PDO coverage with
execute-array parameter parity. It does not repeat CTAS schema-plan coverage,
unknown INSERT target-column coverage, prepared unknown-column rejection,
JSON/WAL/VFS/B-tree corpus work, or source-neutral cleanup.

## Dependency Closure

No new support component is needed. The patch reuses the existing pure-PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, and optional local
native `pdo_sqlite` oracle used by the focused test.
