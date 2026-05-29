# pragma-index-xinfo-foreignkey-current-source-next178

Adds a current-source PRAGMA page that joins `foreign_key_list` child/parent
column rows to the parent key rows exposed by `PRAGMA index_xinfo`.

This slice is deliberately narrower than accepted next170-next175 work:
actions, deferral timing, targeted `foreign_key_check(table)`, and raw
foreign-key-list rows are reused. The new behavior verifies that parent columns
map to `index_xinfo` rows with `key=1`, records rowid-primary-key mappings, and
counts auxiliary index columns that SQLite reports with `key=0` but that must
not satisfy the FK parent key.

WordPress use: copied `wp_options` imports can resume FK repair only after a
missing parent unique key is repaired, while avoiding false positives from
covering/auxiliary index columns.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next178.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next178.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the
existing lane-local schema catalog, `PRAGMA index_xinfo`, `PRAGMA
foreign_key_list`, and current-source cursor primitives.
