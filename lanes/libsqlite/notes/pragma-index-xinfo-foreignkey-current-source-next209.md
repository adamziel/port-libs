# PRAGMA Index XInfo Foreign Key Current Source Next209

This slice adds current/next PRAGMA evidence for SQLite foreign keys that omit
the parent column list (`REFERENCES parent`). SQLite resolves those omitted
parent columns to the parent table primary key, so the child key arity must
match the parent primary-key arity before a copied WordPress schema repair is
admitted.

Focused behavior:

- Composes the accepted `next206` PRAGMA/index/FK current-source page.
- Adds `foreign_key_implicit_parent_primary_key` rows from
  `PRAGMA foreign_key_list` rows whose `to` value is NULL/omitted.
- Compares child column count to `PRAGMA table_info` parent primary-key
  columns, including composite `WITHOUT ROWID` primary keys.
- Reports `valid_implicit_parent_key`, `arity_mismatch`, and
  `missing_parent_primary_key` counts for current/next snapshots.
- Preserves source cursor invalidation when schema records or offsets change.

Verification evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next209.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next209.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted parent UNIQUE coverage, partial-index rejection, parent
  collation mismatch, NULL child-key omission, rowid-alias parent-key coverage,
  and earlier implicit-parent-column FK repair tests.
- This patch only adds omitted parent-column primary-key arity diagnostics for
  current/next PRAGMA pages.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local
  `SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and existing PRAGMA
  current-source pagination helpers.
