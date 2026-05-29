# PRAGMA index_xinfo foreign-key current-source next1167-1182

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
helper with the next1167-1182 action relationship diagnostic page wrappers.
The range continues next1151-1166 and keeps the same `PRAGMA index_xinfo`
child lookup coverage for action, column order, collation, and DESC mismatch
statuses.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11511166Test.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11671182Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1151-1166.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1167-1182.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11511166Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11671182Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1151-1166.php --self-test`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1167-1182.php --self-test`
- `git diff --check`

Scope remains limited to the consolidated SQLite PRAGMA index_xinfo/foreign-key
current-source domain.
