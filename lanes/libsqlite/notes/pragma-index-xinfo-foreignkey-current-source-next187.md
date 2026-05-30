# pragma-index-xinfo-foreignkey-current-source-next187

This slice adds partial UNIQUE parent-index diagnostics beside the accepted
`PRAGMA index_xinfo`, `foreign_key_list`, parent-key admission, parent
collation, and parent sort current-source rows.

Behavior:

- appends `foreign_key_partial_parent_index` rows when FK parent columns match a
  UNIQUE partial index by `PRAGMA index_xinfo` key columns;
- keeps those rows separate from full parent-key admission because upstream
  SQLite does not allow partial indexes to satisfy FK parent keys;
- records the partial index `WHERE` clause, shadowing by a full UNIQUE parent
  key in the next source, source hashes, pagination cursors, and repair deltas;
- includes a Application smoke for copied `wp_options` slug registries where a
  partial active-slug UNIQUE index must not unblock import FK validation.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next187.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next187.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted FK row extraction, FK action/match/deferral/timing
decoration, parent-key mapping, parent collation, parent sort order, child-side
index diagnostics, PRAGMA optimize/index_xinfo/table-info analysis, and the
accepted pager, WAL, B-tree, JSON, encoding, VFS, trigger, and SELECT clusters.
The new surface is partial UNIQUE parent-index candidate visibility and repair
tracking for FK parent-key admission.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `index_list`, `index_xinfo`, and FK catalog parsers.
