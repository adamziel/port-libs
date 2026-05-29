# pragma-index-xinfo-foreignkey-current-source-next183

This slice adds child-side foreign-key index diagnostics beside the accepted
`PRAGMA index_xinfo`, `foreign_key_list`, parent-key admission, constraint-name,
and `foreign_key_check` current-source rows.

Behavior:

- appends `foreign_key_child_index` rows for each FK child column group;
- matches useful child indexes by `PRAGMA index_xinfo` key-column prefix,
  including non-unique, unique, partial, extra-key, and auxiliary-column
  metadata;
- reports current/next missing child indexes and whether the next source
  repaired the copied `wp_options` import plan;
- keeps source hashes and pagination cursors sensitive to child-index DDL.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next183.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next183.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted FK list rows, named constraint decoration, parent
key `index_xinfo` mapping, FK action/deferral/timing behavior, PRAGMA
optimize/index_xinfo/table-info analysis, current/next quickcheck, and the
accepted pager, WAL, B-tree, JSON, encoding, VFS, trigger, and SELECT clusters.
The new surface is child-side FK index coverage derived from current-source
`PRAGMA index_xinfo` rows.

Dependency closure: no new support component is needed. This reuses the
existing schema catalog, `index_list`, `index_xinfo`, and FK catalog parsers.
