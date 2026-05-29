# PRAGMA index_xinfo / foreign-key current-source next319-322

Status: focused PHP behavior growth for `pragma-index-xinfo-foreignkey-current-source-next319-322`.

This slice follows next315-318 and adds action-column-specific diagnostics for `PRAGMA foreign_key_list` replay preflights:

- next319: `ON UPDATE RESTRICT` without a matching child lookup index prefix.
- next320: `ON DELETE RESTRICT` without a matching child lookup index prefix.
- next321: `ON UPDATE NO ACTION` without a matching child lookup index prefix.
- next322: `ON DELETE NO ACTION` without a matching child lookup index prefix.

Validation targets:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext319322Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next319-322.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext315318Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext319322Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next319-322.php --self-test`
- `git diff --check`

Non-overlap: this stays inside the existing PRAGMA index_xinfo / foreign-key current-source helper and matching tests/example/note. It does not touch progress files, lane status, dashboards, JSON, B-tree, WAL, VFS, planner, or unrelated PRAGMA surfaces.

Next slice: continue after next322 with remaining action-column or relationship diagnostics that need current-source pagination.
