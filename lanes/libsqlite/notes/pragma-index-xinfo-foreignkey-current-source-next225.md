# PRAGMA index_xinfo / foreign-key current-source next225

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page225()` for a focused `PRAGMA foreign_key_list` action-clause source page. The slice records `ON UPDATE` and `ON DELETE` actions alongside current/next PRAGMA source IDs so copied WordPress schemas can compare cascade, restrict, set-null, and set-default behavior before enabling FK checks.

Coverage:

- `actionClauseRows225()` normalizes action names and emits one row per FK column.
- `page225()` layers over the accepted match-clause page223 source and appends action summaries, counts, deltas, cursor validation, and the `sqlite-pragma-foreign-key-action-clauses` dependency marker.
- WordPress smoke example: `lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next225.php`.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext225Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next225.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext225Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next225.php --self-test`

Non-overlap: this fills the PRAGMA next224-227 gap without touching status/dashboard/private files. It avoids next224 parent collation matching, next226 missing parent-table catalog checks, next227 child-index suffix diagnostics, and next228 parent sort-order compatibility.
