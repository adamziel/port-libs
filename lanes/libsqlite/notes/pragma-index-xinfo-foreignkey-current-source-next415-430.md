# PRAGMA index_xinfo foreign_key current-source next415-430

Date: 2026-05-29

This slice continues the merged next399-414 action-relationship diagnostics for
`SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`. It adds page methods
`page415()` through `page430()` for next-source foreign-key action rows whose
current-source child lookup index is clean while the next source introduces an
order, collation, or DESC mismatch visible through `PRAGMA index_xinfo`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext415430Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next415-430.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext415430Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next415-430.php --self-test
git diff --check
```

Expected result: the focused TestRunner file passes 16 next-only cases with 9
assertions each, the example self-test reports every implemented page from 415
through 430, and no broad-suite or upstream `testfixture` run is claimed.

Non-overlap: this only touches the aggregate PRAGMA index_xinfo/foreign_key
current-source lane, its matching focused test, example, and note for next415
through next430.
