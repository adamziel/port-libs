# pragma-index-xinfo-foreignkey-current-source-next179

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179`, a
current-source PRAGMA helper for copied WordPress schema diagnostics where
foreign-key constraint names are single-quoted in `sqlite_schema` DDL.

Behavior covered:

- single-quoted column-level constraint names, including escaped single quotes;
- single-quoted table-level `CONSTRAINT ... FOREIGN KEY` names;
- normalized base-catalog handoff so inherited `index_xinfo`, FK action, and
  deferral rows still resolve through existing PRAGMA helpers;
- source-id, pagination, stale-cursor, current/next delta, and row decoration
  changes for the single-quoted constraint-name source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next179.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next179.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext179Test.php`
  - `1 test files, 75 assertions, 0 failures`
  - 67 focused PASS lines.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next179.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next179 self-test passed`

Non-overlap:

This avoids accepted next176 named constraint decoration, next175
`foreign_key_list` row pagination, next171/173 deferral/timing decoration,
next167 action decoration, and accepted PRAGMA optimize/index_xinfo/table-info
analysis. The new surface is SQLite-compatible single-quoted FK constraint
identifier recovery while reusing the existing current-source `index_xinfo`
and `foreign_key_check` rows.

Dependency closure:

No new support component is needed. The slice reuses the existing schema
catalog, `index_xinfo`, foreign-key extraction, timing/action, pagination, and
current-source cursor helpers.
