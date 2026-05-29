# PRAGMA Index XInfo Foreign Key Current Source Next210

This slice adds current/next PRAGMA evidence for foreign keys that use
`ON UPDATE SET DEFAULT` or `ON DELETE SET DEFAULT`. SQLite exposes the action
through `PRAGMA foreign_key_list`; the child columns also need concrete
defaults from `PRAGMA table_info` before a copied WordPress schema repair can
safely rely on SET DEFAULT behavior.

Focused behavior:

- Composes the accepted `next209` PRAGMA/index/FK current-source page.
- Adds `foreign_key_set_default_child_default` rows for FK groups whose update
  or delete action is `SET DEFAULT`.
- Joins FK child columns to `PRAGMA table_info` default values.
- Reports missing child defaults for current/next snapshots and marks a repair
  when the next schema supplies the defaults.
- Preserves source cursor invalidation when schema records or offsets change.

Verification evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next210.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next210.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted FK action listing, deferral timing, parent UNIQUE/partial/
  collation/order admission, null-child omission, rowid-alias parent-key, and
  implicit parent primary-key arity checks.
- The new surface is the `SET DEFAULT` action plus child-column default
  availability check tied to paged `index_xinfo`/`foreign_key_list` output.

Dependency closure:

- No new support component is needed. The slice reuses lane-local
  `SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and existing PRAGMA
  current-source pagination helpers.
