# PRAGMA index_xinfo foreign-key current-source next271-274

Adds after-current transition pages for the accepted PRAGMA index_xinfo /
foreign-key child-action lookup path:

- `page271`: ON DELETE/UPDATE CASCADE child lookup transitions.
- `page272`: SET NULL child lookup transitions.
- `page273`: SET DEFAULT child lookup transitions.
- `page274`: RESTRICT child lookup transitions.

The pages build on next263-266 action-specific rows and report whether a child
lookup index blocker was repaired, regressed, stayed blocked, or remained OK
between current and next schema sources. The source cursor includes the
underlying page263-266 source hash plus the deterministic after-current
transition summaries.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext271274Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext271274Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next271-274.php --self-test`
