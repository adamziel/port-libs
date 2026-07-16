# PRAGMA index_xinfo foreign-key current-source next1103-1118

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
class with page1103 through page1118. The slice continues directly after
next1087-1102 using the existing `actionRelationshipDiagnosticPage311` helper
for mixed foreign-key action relationship diagnostics covering order,
collation, and DESC child lookup index mismatches.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11031118Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1103-1118.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10871102Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11031118Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1087-1102.php --self-test`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1103-1118.php --self-test`
- `git diff --check`
