# PRAGMA index_xinfo foreign-key current-source next1055-1070

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
class with page1055 through page1070. The slice continues the existing
`actionRelationshipDiagnosticPage311` pattern for mixed foreign-key action
relationship diagnostics covering order, collation, and DESC child lookup
index mismatches.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10551070Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1055-1070.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10391054Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10551070Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1039-1054.php --self-test`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next1055-1070.php --self-test`
- `git diff --check`
