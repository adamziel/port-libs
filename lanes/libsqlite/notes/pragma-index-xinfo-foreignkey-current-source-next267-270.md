# PRAGMA index_xinfo foreign-key current-source next267-270

Adds real page267-page270 behavior for action-bearing foreign keys whose child
tables have no matching child lookup index by `PRAGMA index_xinfo` key prefix.

- next267: `RESTRICT` action without a child lookup index.
- next268: `CASCADE` action without a child lookup index.
- next269: `SET NULL` action without a child lookup index.
- next270: `SET DEFAULT` action without a child lookup index.

The slice builds on the current-source PRAGMA class and uses
`PRAGMA foreign_key_list`, `PRAGMA index_list`, and `PRAGMA index_xinfo` derived
catalog rows. It does not update dashboard, status, progress, supervisor, or
private lane files.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext267270Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next267-270.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext267270Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next267-270.php --self-test`
- `git diff --check`
