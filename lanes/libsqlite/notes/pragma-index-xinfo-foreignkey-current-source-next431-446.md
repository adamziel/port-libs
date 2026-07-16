# PRAGMA index_xinfo foreign_key current-source next431-446

Date: 2026-05-29

This slice continues the merged next415-430 action-relationship diagnostics for
`SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`. It adds page methods
`page431()` through `page446()` for next-source foreign-key action rows whose
current-source child lookup index is clean while the next source introduces an
order, collation, or DESC mismatch visible through `PRAGMA index_xinfo`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext431446Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next431-446.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext431446Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next431-446.php --self-test
git diff --check
```

Expected result: the focused TestRunner file passes 16 next-only cases with 9
assertions each, the example self-test reports every implemented page from 431
through 446, and no broad-suite or upstream `testfixture` run is claimed.

Non-overlap: this only touches the aggregate PRAGMA index_xinfo/foreign_key
current-source lane, its matching focused test, example, and note for next431
through next446.
