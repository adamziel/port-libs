# PRAGMA index_xinfo / foreign-key current-source next315-318

Status: focused PHP behavior growth for `pragma-index-xinfo-foreignkey-current-source-next315-318`.

This slice follows next311-314 and adds action-column-specific diagnostics for `PRAGMA foreign_key_list` replay preflights:

- next315: `ON UPDATE SET DEFAULT` whose child column is `NOT NULL DEFAULT NULL`.
- next316: `ON DELETE SET DEFAULT` whose child column is `NOT NULL DEFAULT NULL`.
- next317: `ON UPDATE CASCADE` without a matching child lookup index prefix.
- next318: `ON DELETE CASCADE` without a matching child lookup index prefix.

Validation targets:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext315318Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next315-318.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext311314Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext315318Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next315-318.php --self-test`
- `git diff --check`

Non-overlap: this stays inside the existing PRAGMA index_xinfo / foreign-key current-source helper and matching tests/example/note. It does not touch progress files, lane status, dashboards, JSON, B-tree, WAL, VFS, planner, or unrelated PRAGMA surfaces.

Next slice: continue after next318 with remaining action-column or relationship diagnostics that need current-source pagination.
