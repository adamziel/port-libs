# PRAGMA index_xinfo/FK current-source next243

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered on the
accepted next241 PRAGMA/FK page. The new current-source behavior joins
`PRAGMA foreign_key_list` child/parent columns to `table_info` declared types
and reports the parent affinity SQLite applies during FK comparison.

This covers Application import/copy DDL where copied rows may arrive with text
or blob staging columns before the final schema replay:

- shorthand `REFERENCES wp_posts` resolves through the parent primary key and
  reports the parent `INTEGER` affinity;
- explicit parent columns report TEXT, NUMERIC, REAL, and INTEGER affinity
  application;
- current staging schemas with mismatched child affinities report blockers;
- repaired next schemas clear those affinity mismatches while preserving the
  current-source pagination and cursor checks.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 69 assertions, 0 failures`
  - 53 focused `PASS` lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next243.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next243 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next243.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next243.php`
- `git diff --check -- lanes/libsqlite`
  - clean

Non-overlap:

- Avoids accepted next240/next241 implicit parent primary-key and raw NULL
  parent-reference rows by using them only as inputs to parent-affinity
  diagnostics.
- Avoids accepted parent UNIQUE arity/prefix, descending key, collation,
  action, deferral, match-clause, index_xinfo auxiliary-row, and rootpage
  integrity PRAGMA/FK clusters.
- Does not touch JSON-table, WAL, B-tree, VFS, SELECT, encoding, or suite
  runner surfaces.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSchemaRecord`,
  `SQLitePragmaSchemaCatalog`, and the accepted current-source PRAGMA/FK
  pagination chain.
