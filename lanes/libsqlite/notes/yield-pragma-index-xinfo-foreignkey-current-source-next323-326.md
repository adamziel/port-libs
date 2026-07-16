## PRAGMA Index Xinfo Foreign Key Current Source Next323-326

Adds follow-on PRAGMA index_xinfo and foreign_key_list preflight slices for RESTRICT and NO ACTION relationships whose child lookup candidate is only a partial index.

- `page323`: update RESTRICT partial child lookup index.
- `page324`: delete RESTRICT partial child lookup index.
- `page325`: update NO ACTION partial child lookup index.
- `page326`: delete NO ACTION partial child lookup index.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext323326Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next323-326.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext319322Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext323326Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next323-326.php --self-test`
- `git diff --check`
