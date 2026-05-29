# pragma-index-xinfo-foreignkey-current-source-next190

This slice adds expression-parent UNIQUE-index diagnostics beside the accepted
`PRAGMA index_xinfo` and foreign-key current-source rows.

Behavior:

- appends `foreign_key_expression_parent_index` rows when a foreign-key parent
  key has a UNIQUE non-partial parent index with matching arity but at least one
  expression term from `PRAGMA index_xinfo`;
- marks those rows as blockers because SQLite foreign-key parent keys must be
  named table columns, not expression terms;
- records expression/ordinary term counts, shadowing by a repaired full parent
  key, pagination cursors, source hashes, and repaired deltas;
- includes a WordPress smoke for copied option slug imports where an index on
  `lower(locale)` cannot satisfy `REFERENCES wp_option_slug(slug, locale)`.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next190.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next190.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next187 partial UNIQUE parent-index diagnostics,
next186 child-index collation, next185 NULL child-key omissions, next184
parent sort order, next182 parent collation admission, next178 parent-key
mapping, PRAGMA optimize/index_xinfo/table-info analysis, recursive FK catalog
output, pointer-map integrity checks, and queued WAL/VFS/B-tree/JSON planner
surfaces.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `index_list`, `index_xinfo`, FK catalog parsers, and
current-source cursor conventions.
