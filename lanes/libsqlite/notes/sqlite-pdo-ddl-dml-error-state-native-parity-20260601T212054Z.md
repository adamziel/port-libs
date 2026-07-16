# SQLitePDO DDL/DML Error State Native Parity

Slice: `sqlite-pdo-ddl-dml-error-state-native-parity-20260601T212054Z`

Base: `f8405410a9c23dda1b6573b28dc0ef5eb942efb5`

Source truth: local native `pdo_sqlite` on PHP reported:

- duplicate `CREATE TABLE app_settings (...)` fails in `exec()` and `prepare()` with `['HY000', 1, 'table app_settings already exists']`;
- malformed simple DDL `CREATE TABLE invalid_ddl (123bad TEXT)` fails with `['HY000', 1, 'unrecognized token: "123bad"']`;
- empty simple DDL column lists fail with `['HY000', 1, 'near ")": syntax error']`;
- incomplete simple DDL input fails with `['HY000', 1, 'incomplete input']`;
- silent mode returns `false`, preserves the connection-scoped error info until the next successful operation, and later DML clears the connection error state.

Before this patch, the polyfill accepted duplicate simple `CREATE TABLE` and overwrote the in-memory/file-backed table state, while malformed DDL reported implementation messages such as `SQLitePDO CREATE TABLE column is malformed`.

Implemented behavior:

- `SQLitePDO::prepare()` now validates supported simple `CREATE TABLE` DDL for native prepare-time duplicate and malformed DDL errors.
- `SQLitePDO::exec()` now rejects duplicate table creation, including case-insensitive duplicate names, before mutating table state.
- `SQLitePdoFileImage::parseCreateTableDefinition()` now normalizes malformed simple DDL diagnostics to native SQLite messages for the covered tokenizer, empty-list, and incomplete-input cases.
- Added a generic `application-pdo-ddl-error-state.php` smoke showing duplicate/malformed DDL preserving a file-backed `app_settings` table and follow-up DML clearing the error state.

Focused evidence:

- Red probe before implementation showed native duplicate DDL throwing `table test already exists`, while the polyfill returned `0` and cleared error state.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoDdlDmlErrorStateNativeParityTest.php` passed: `1 test files, 157 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoDdlDmlErrorStateNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorPersistenceParityTest.php lanes/libsqlite/tests/SQLitePdoInvalidDmlNativeParityAuditTest.php lanes/libsqlite/tests/SQLitePdoInvalidGroupByNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePDORegressionTest.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php` passed: `8 test files, 992 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pdo-ddl-error-state.php --self-test` passed.
- `php -l lanes/libsqlite/src/SQLitePDO.php`, `php -l lanes/libsqlite/src/SQLitePdoFileImage.php`, `php -l lanes/libsqlite/tests/SQLitePdoDdlDmlErrorStateNativeParityTest.php`, and `php -l lanes/libsqlite/examples/application-pdo-ddl-error-state.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 8 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

Dashboard delta:

- New focused TestRunner PASS cases: `+2`.
- `phpPass`: `6272894 -> 6272896`.
- Mapped upstream denominator: unchanged at `1589 / 1589`.

Non-overlap:

- This does not repeat accepted PDO invalid-column, invalid GROUP BY, invalid INSERT, file persistence, silent statement execute, JSON101 atomicity, or UPSERT RETURNING coverage.
- This patch is limited to simple `CREATE TABLE` DDL error-state parity and follow-up DML error clearing.
- It intentionally does not implement `CREATE INDEX`, `DROP TABLE`, or broad DDL execution support.

Dependency closure:

- No new support component is needed.
- The slice reuses `SQLitePDO`, `SQLitePDOStatement`, `SQLitePdoFileImage`, and native `pdo_sqlite` as the local oracle when available.
