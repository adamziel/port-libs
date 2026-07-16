# PRAGMA index_xinfo foreign-key current-source next1135-1150

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
class with page1135 through page1150. The slice continues directly after
next1119-1134 using the existing `actionRelationshipDiagnosticPage311` helper
for mixed foreign-key action relationship diagnostics covering order,
collation, and DESC child lookup index mismatches.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11351150Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1135-1150.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11191134Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext11351150Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1119-1134.php --self-test`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1135-1150.php --self-test`
- `git diff --check`
