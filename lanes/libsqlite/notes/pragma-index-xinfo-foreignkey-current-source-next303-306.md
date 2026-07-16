# PRAGMA index_xinfo / foreign_key current-source next303-306

This slice keeps the PRAGMA/FK current-source lane moving after the next295-302 readiness window by adding four isolated child-key diagnostics on top of the accepted next287-294 child lookup surface.

- next303: empty FK child-column list.
- next304: nullable child column used with a CASCADE action.
- next305: child lookup index key prefix matches the FK columns but uses a non-BINARY collation.
- next306: child lookup index key prefix matches the FK columns but is descending.

The pages reuse the existing resumable child-key diagnostic pager, keep rows under `foreign_key_child_key_diagnostic`, and add only PRAGMA index_xinfo/foreign-key source, tests, examples, and notes.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext303306Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next303-306.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext303306Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next303-306.php --self-test`
- `git diff --check`
