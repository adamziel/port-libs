# SQLitePDO User Snippet Error Regression - 2026-06-01 21:46 UTC

Added focused coverage for the public `SQLitePDO` snippets using `sqlite:./test.sqlite`:

- default error mode creates the relative file, rejects `INSERT INTO test (namedd) VALUES ('Janet')` with `HY000/1`, and leaves the table empty after reopening;
- `exec('INSERT INTO test (name) VALUES (?)', ['John'])` inserts and persists the string value through the prepared-statement path.

Verification:

- `php -l lanes/libsqlite/tests/SQLitePDORegressionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePDORegressionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoNativeInvalidInsertFilePersistenceExecParamsAuditTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorFalseParityTest.php lanes/libsqlite/tests/SQLitePdoDdlDmlErrorStateNativeParityTest.php lanes/libsqlite/tests/SQLitePdoNativeErrorMatrixFileMemoryErrmodesTest.php`
- `git diff --check -- lanes/libsqlite`
