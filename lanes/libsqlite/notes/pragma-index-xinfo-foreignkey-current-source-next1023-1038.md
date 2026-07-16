# PRAGMA index_xinfo foreign-key current-source next1023-1038

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
class with page1023 through page1038. The slice reuses the existing
`actionRelationshipDiagnosticPage311` pattern for mixed foreign-key action
relationship diagnostics covering order, collation, and DESC child lookup
index mismatches.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10231038Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1023-1038.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10071022Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext10231038Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1007-1022.php --self-test`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next1023-1038.php --self-test`
- `git diff --check`
