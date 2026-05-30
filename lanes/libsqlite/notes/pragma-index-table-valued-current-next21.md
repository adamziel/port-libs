### 2026-05-27 PRAGMA index/table table-valued current-source next21

This slice adds native PHP coverage for SQLite schema PRAGMA table-valued
functions beyond the already accepted `pragma_foreign_key_list(...)` surface:
`pragma_table_info`, `pragma_table_xinfo`, `pragma_index_list`,
`pragma_index_info`, and `pragma_index_xinfo`.

The implementation reuses the existing direct PRAGMA row producers and current
source resolution, so unqualified table-valued functions search `temp`, `main`,
then attached databases, while the optional second schema argument pins lookup.
The row cursor API also works over table-valued PRAGMA results and freezes the
resolved schema/rows at open time.

Verification:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexTableValuedCurrentNext21Test.php
Focused test run: 1 selected test files (root lock skipped)
60 PASS lines, 73 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyListCurrentNext18Test.php lanes/libsqlite/tests/SQLitePragmaIndexTableInfoCursorTest.php lanes/libsqlite/tests/SQLitePragmaIndexTableValuedCurrentNext21Test.php
Focused test run: 3 selected test files (root lock skipped)
172 PASS lines, 246 assertions, 0 failures

$ php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php
$ php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
$ php -l lanes/libsqlite/tests/SQLitePragmaForeignKeyListCurrentNext18Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaForeignKeyListCurrentNext18Test.php
$ php -l lanes/libsqlite/tests/SQLitePragmaIndexTableValuedCurrentNext21Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexTableValuedCurrentNext21Test.php
$ php -l lanes/libsqlite/examples/application-pragma-index-table-valued-current-next21.php
No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-table-valued-current-next21.php

$ php lanes/libsqlite/examples/application-pragma-index-table-valued-current-next21.php
Printed copied wp_options table-valued PRAGMA current-source JSON with temp
wp_options columns, main option-name index metadata, and network
wp_sitemeta index collation metadata.

$ git diff --check -- lanes/libsqlite
No whitespace errors.
```

Status delta: `lane-status.json` `phpPass` moves from `7262` to `7322`, exactly
the 60 new PASS lines in `SQLitePragmaIndexTableValuedCurrentNext21Test.php`.

Dependency closure: no new support component is needed; this reuses the
lane-local schema catalog, PRAGMA row parser, and row cursor primitives.

Non-overlap: this does not repeat accepted direct PRAGMA row cursors,
`pragma_foreign_key_list(...)`, JSON table cursor/source wiring, SELECT SQL
text dispatch, B-tree page move/freeblock/overflow clusters, WAL
savepoint/rollback/checkpoint clusters, VFS writer/lock/sync clusters, or
Unicode GLOB work. It adds the missing table-valued PRAGMA index/table function
form over the existing schema rowsets.
