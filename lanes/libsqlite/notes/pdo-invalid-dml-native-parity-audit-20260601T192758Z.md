# SQLitePDO invalid DML native parity audit

Micro-slice: `pdo-invalid-dml-native-parity-audit-20260601T192758Z`

## Behavior

This slice aligns file-backed `SQLitePDO` with native `pdo_sqlite` for invalid
INSERT, UPDATE, and DELETE scalar-expression DML paths:

- Invalid DML expressions such as `missing_value || '-suffix'` are rejected
  during `prepare()` and direct `exec()` with native-style
  `no such column: missing_value` error info.
- Invalid numbered DML variables such as `?0 + 1` are rejected before row
  mutation with native-style `variable number must be between ?1 and ?32766`.
- Direct `exec()` now reuses the same DML validation as `prepare()` before
  applying INSERT/UPDATE/DELETE mutations.
- Surplus prepared UPDATE/DELETE execute parameters remain statement-scoped,
  while the polyfill-only parameterized `exec($sql, $params)` helper keeps
  connection-scoped `column index out of range` parity.
- File-backed images stay byte-identical after each rejected DML operation and
  reopen with the original rows.

The application smoke uses generic `app_settings` rows and verifies invalid
UPDATE expressions plus surplus UPDATE/DELETE parameter paths without adding
domain-specific source APIs.

## Evidence

Red-first:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php`

Failed before the source change with `1 test files, 1 assertions, 1 failures`
because `UPDATE app_settings SET key_value = missing_value || '-suffix' ...`
reached statement execute instead of failing during prepare like native
`pdo_sqlite`.

Passing focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php`
  passed with `1 test files, 148 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php`
  passed with `6 test files, 785 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-invalid-dml-native-parity-audit.php --self-test`
  passed.

## Non-overlap

This does not repeat accepted invalid target-column, missing-table, silent
false-return, INSERT omitted-parameter, statement error-persistence, or file
rollback coverage. It owns the narrower invalid DML scalar-expression and
UPDATE/DELETE surplus-parameter parity surface.

Expected focused PASS movement is `+1`: the new audit file adds one focused
TestRunner case with 148 behavior assertions. Mapped upstream coverage remains
unchanged.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, the existing
INSERT/UPDATE/DELETE parser helpers, and local native `pdo_sqlite` only as a
focused parity oracle in tests.
