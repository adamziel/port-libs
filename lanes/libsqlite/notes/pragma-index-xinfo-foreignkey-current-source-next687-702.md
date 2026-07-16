# PRAGMA index_xinfo foreign_key current-source next687-702

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
current-source page lane with next687 through next702 as the direct follow-on to
next671-686.

The slice keeps the existing action relationship diagnostic factory and adds no
new numbered source class. Coverage repeats the 16 prepared mixed-action child
lookup mismatch receipts across order, collation, and DESC index_xinfo shapes.

Validation:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext687702Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next687-702.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext687702Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext671686Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext687702Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next687-702.php --self-test
git diff --check
```

Scope is limited to the consolidated current-source lane, its matching focused
test, example, and note for next687 through next702.
