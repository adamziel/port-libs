# PRAGMA index_xinfo foreign_key current-source next511-526

Date: 2026-05-29

This slice is a direct follow-on to merged next495-510 action-relationship
diagnostics for `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`. It adds
page methods `page511()` through `page526()` for the next page window of
next-source foreign-key action rows whose current-source child lookup index is
clean while the next source introduces an order, collation, or DESC mismatch
visible through `PRAGMA index_xinfo`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext511526Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next511-526.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext511526Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next511-526.php --self-test
git diff --check
```

Expected result: the focused TestRunner file passes 16 next-only cases with 9
assertions each, the example self-test reports every implemented page from 511
through 526, and no broad-suite or upstream `testfixture` run is claimed.

Non-overlap: this only touches the aggregate PRAGMA index_xinfo/foreign_key
current-source lane, its matching focused test, example, and note for next511
through next526.
