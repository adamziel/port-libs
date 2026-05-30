# PRAGMA index_xinfo foreign_key current-source next463-478

Date: 2026-05-29

This slice continues the merged next447-462 action-relationship diagnostics for
`SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`. It adds page methods
`page463()` through `page478()` for next-source foreign-key action rows whose
current-source child lookup index is clean while the next source introduces an
order, collation, or DESC mismatch visible through `PRAGMA index_xinfo`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext463478Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next463-478.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext463478Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next463-478.php --self-test
git diff --check
```

Expected result: the focused TestRunner file passes 16 next-only cases with 9
assertions each, the example self-test reports every implemented page from 463
through 478, and no broad-suite or upstream `testfixture` run is claimed.

Non-overlap: this only touches the aggregate PRAGMA index_xinfo/foreign_key
current-source lane, its matching focused test, example, and note for next463
through next478.
