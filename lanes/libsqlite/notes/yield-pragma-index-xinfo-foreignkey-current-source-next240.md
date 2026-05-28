# PRAGMA index_xinfo / foreign_key current-source next240

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240`, a lane-local catalog page that identifies `PRAGMA foreign_key_list` rows whose raw schema used shorthand `REFERENCES parent` with omitted parent columns. The helper joins that raw SQL signal back to the already resolved foreign-key rows and parent `table_info` primary-key metadata so schema comparisons can distinguish:

- shorthand single-column parent primary-key mappings;
- shorthand composite parent primary-key mappings;
- shorthand child/parent primary-key arity mismatches;
- explicit parent-column references, which are intentionally ignored by this slice.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240Test.php`
  - `1 test files, 56 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next240.php`
  - self-test passed
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240Test.php`
  - no syntax errors
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next240.php`
  - no syntax errors

## Non-Overlap

This avoids accepted/current PRAGMA surfaces for exact parent UNIQUE arity and prefix-only blockers (`next237`), descending parent UNIQUE indexes (`next235`), quoted/case-folded parent key names (`next236`), collation checks (`next220`), and earlier foreign-key/index_xinfo action/index catalog rows. It also avoids JSON-table, WAL, B-tree, encoding, and VFS accepted clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing `SQLiteSchemaRecord`, `SQLitePragmaSchemaCatalog`, and `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175` parsing. Existing base parsing still rejects unresolvable parent tables with no primary key before next240 can page them; this handoff covers the resolvable SQLite shorthand-primary-key behavior.
