# PRAGMA index_xinfo foreign_key current-source next575-590

Date: 2026-05-29

This slice is a direct follow-on to merged next559-574 action-relationship
diagnostics for `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`. It adds
page methods `page575()` through `page590()` for the next page window of
next-source foreign-key action rows whose current-source child lookup index is
clean while the next source introduces an order, collation, or DESC mismatch
visible through `PRAGMA index_xinfo`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext575590Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next575-590.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext575590Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next575-590.php --self-test
git diff --check
```

Expected result: the focused TestRunner file passes 16 next-only cases with 9
assertions each, the example self-test reports every implemented page from 575
through 590, and no broad-suite or upstream `testfixture` run is claimed.

Non-overlap: this only touches the aggregate PRAGMA index_xinfo/foreign_key
current-source lane, its matching focused test, example, and note for next575
through next590.
