# SQLitePDO invalid GROUP BY column native parity

Micro-slice: `pdo-native-error-parity-invalid-column-file-persistence-20260601T204803Z`

## Behavior

File-backed `SQLitePDO` now normalizes the remaining grouped SELECT missing
column diagnostic to match native `pdo_sqlite`:

- `SELECT key_name FROM app_settings GROUP BY missing_key`
  fails with `['HY000', 1, 'no such column: missing_key']`.
- Prepared grouped SELECTs fail during `prepare()` like native PDO, not later
  during statement `execute()`.
- Query and prepare failures keep the connection-scoped error state, do not
  create a statement for prepared failures, leave the SQLite file bytes
  unchanged, and reopen with the original rows.

The source fix maps the internal
`SQLite GROUP BY row is missing column ...` executor exception into the same
native-style column-resolution message already used for SELECT expression and
predicate missing-column failures.

## Evidence

Red-first probe before the fix:

`SELECT key_name FROM app_settings GROUP BY missing_key` prepared successfully
against `SQLitePDO` and failed only during statement execute with
`SQLite GROUP BY row is missing column missing_key`, while native `pdo_sqlite`
failed during prepare with `no such column: missing_key`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php`
  passed with `1 test files, 44 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php`
  passed with `6 test files, 826 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-invalid-group-by-persistence.php --self-test`
  passed.

## Non-overlap

This does not repeat accepted invalid INSERT target columns, invalid scalar
DML expressions, missing table identifiers, silent false-return behavior,
surplus execute parameters, or statement error-persistence coverage. It owns
the narrower invalid grouped SELECT column-resolution path and its file-backed
PDO error-state parity.

Expected focused PASS movement is `+1` from the new focused TestRunner case.
Mapped upstream coverage remains unchanged.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePDO`,
`SQLitePDOStatement`, `SQLitePdoFileImage`, `SQLiteSelectSql`, and local native
`pdo_sqlite` only as a focused parity oracle in tests.
