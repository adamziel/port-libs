# PRAGMA index_xinfo foreign_key current-source next623-638

Date: 2026-05-29

This slice is a direct follow-on to merged next607-622 action-relationship
diagnostics for `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`. It extends
the established canonical source class with page methods `page623()` through
`page638()` for the next page window of next-source foreign-key action rows
whose current-source child lookup index is clean while the next source
introduces an order, collation, or DESC mismatch visible through
`PRAGMA index_xinfo`.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext623638Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next623-638.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext623638Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next623-638.php --self-test
git diff --check
```

Expected result: the focused TestRunner file passes 16 next-only cases with 9
assertions each, the example self-test reports every implemented page from 623
through 638, and no broad-suite or upstream `testfixture` run is claimed.

Non-overlap: this only touches the aggregate PRAGMA index_xinfo/foreign_key
current-source lane, its matching focused test, example, and note for next623
through next638. A new numbered source class was not created because the local
pattern is to extend the canonical aggregate source class for this domain.
