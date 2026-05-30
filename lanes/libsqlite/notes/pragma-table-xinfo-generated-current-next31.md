# PRAGMA table_xinfo generated current/next31

2026-05-27 isolated slice `yield-sqlite-pragma-table-xinfo-generated-current-next31`.

- Tightened `SQLitePragmaSchemaCatalog` generated-column detection so `PRAGMA table_xinfo` marks hidden code `2`/`3` only for actual generated `AS (...)` expressions.
- Added focused coverage for normal `PRAGMA table_xinfo`, table-valued `pragma_table_xinfo()`, attached-schema current source resolution, and cursor `current()`/`next()` walking across virtual/stored generated columns.
- Added false-positive coverage for visible columns whose declared type/default/check text contains `AS` or `CURRENT_TIMESTAMP`; these remain visible in both `table_info` and `table_xinfo`, matching the local sqlite3 oracle sample:
  `0|a|AS TEXT|0||0|0`, `2|c|TEXT|0||0|2`, `3|d|INT|0||0|3`.
- Added `examples/application-pragma-table-xinfo-generated-current-next31.php` for copied `wp_options` catalog preflight without ext/sqlite.

Verification output:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaTableXinfoGeneratedCurrentNext31Test.php`
  - `1 test files, 68 assertions, 0 failures`
  - 52 `PASS pragma table_xinfo generated current next31 ...` lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteGeneratedColumnCheckConstraintCorpusTest.php lanes/libsqlite/tests/SQLitePragmaIndexTableValuedCurrentNext21Test.php lanes/libsqlite/tests/SQLitePragmaIndexTableInfoCursorTest.php lanes/libsqlite/tests/SQLitePragmaTableXinfoGeneratedCurrentNext31Test.php`
  - `5 test files, 353 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-table-xinfo-generated-current-next31.php`
  - emitted `status: ok`, `tableXinfoCount: 9`, `tableInfoCount: 6`, and hidden codes `2`/`3` for generated columns while literal `AS` and `CURRENT_TIMESTAMP` columns remain visible.
- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaTableXinfoGeneratedCurrentNext31Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-table-xinfo-generated-current-next31.php`
  - all reported `No syntax errors detected`.
- `git diff --check -- lanes/libsqlite`
  - no output.

Non-overlap:

This avoids accepted PRAGMA function/module/collation metadata, table-valued index pragma cursor coverage, generated dependency-cycle planning, generated CHECK/schema catalog handling, SELECT SQL, JSON table, B-tree, WAL, and VFS clusters. The new behavior is bounded to `PRAGMA table_xinfo` generated-column hidden metadata and cursor/source behavior, plus literal `AS` false-positive prevention.
