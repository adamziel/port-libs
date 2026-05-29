# PRAGMA index_xinfo / foreign_key current-source next161

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a catalog-derived current/next PRAGMA helper that resolves implicit parent columns for `REFERENCES parent` declarations from the parent table primary key.
- Covers inline integer primary keys and composite table-level primary keys, including `WITHOUT ROWID` WordPress-style option-name catalogs.
- Keeps next159 explicit-parent-column behavior intact and continues to reject implicit references when the parent table has no primary key.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- Result: `1 test files, 73 assertions, 0 failures` with 65 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next161.php --self-test`

## Non-Overlap

This avoids accepted next158/next159 surfaces for explicit FK parent columns, rootpage/integrity pagination, and catalog-derived explicit parent-column current/next comparison. The new behavior is implicit parent-primary-key resolution needed before `foreign_key_check` and parent-index admission can count `REFERENCES parent` declarations.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePragmaSchemaCatalog`, `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, and existing FK/index integrity helpers.
