# PRAGMA Index XInfo Foreign Key Current Source Next211

This slice adds a current/next PRAGMA evidence layer for foreign-key child
columns that remain nullable. SQLite does not check a foreign key when any
child-key column is NULL, so copied WordPress import schemas need this
diagnostic before treating a child key as enforced.

Focused behavior:

- Composes the accepted `next209` PRAGMA/index/FK current-source page.
- Adds deterministic `foreign_key_child_nullability` rows from
  `PRAGMA foreign_key_list` child groups and `PRAGMA table_info` `notnull`/PK
  metadata.
- Reports `nullable_child_key` versus `all_not_null_child_key` counts for
  current and next schema snapshots.
- Treats child `INTEGER PRIMARY KEY` columns as not-null child keys.
- Preserves source cursor invalidation when schema records or page offsets
  change.

Verification evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 63 assertions, 0 failures`
  - `55` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next211.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next211 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next211.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted parent UNIQUE coverage, parent collation/order/permutation,
  child prefix presence/quality, rowid-alias parent-key coverage, and omitted
  parent primary-key arity diagnostics.
- This patch only adds child-key nullability diagnostics for FK groups using
  `foreign_key_list` plus `table_info`.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local
  `SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and existing PRAGMA
  current-source pagination helpers.
