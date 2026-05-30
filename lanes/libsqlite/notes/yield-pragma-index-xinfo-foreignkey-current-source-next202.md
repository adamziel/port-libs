# PRAGMA index_xinfo/foreign_key_list current-source next202

- Slice: table-valued `pragma_foreign_key_list(...)` admission for the combined current/next `index_xinfo` and `foreign_key_list` catalog page.
- Behavior: preserves source identity separately for statement-form `PRAGMA foreign_key_list(...)` and table-valued `pragma_foreign_key_list(...)`, while keeping the same catalog row deltas for copied Application taxonomy schema reparses.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php` passed with `1 test files, 85 assertions, 0 failures` and 71 PASS lines.
- Example target: `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next202.php --self-test`.
- Non-overlap: avoids accepted next196 statement-form combined pagination, accepted table-valued index_xinfo-only handling, accepted FK recursive/table-valued catalog slices, and recent PRAGMA integrity/FK pointer-map surfaces.
- Dependency closure: no new support component is needed; this reuses the existing `SQLitePragmaSchemaCatalog` table-valued PRAGMA parser and schema-record catalog.
