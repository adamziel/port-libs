# PRAGMA index_xinfo / foreign-key action columns current-source next213

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a
current-source PRAGMA diagnostic layered on the accepted next212
`index_xinfo` / foreign-key child-action lookup page.

Behavior:

- reads `PRAGMA foreign_key_list` action metadata for `SET NULL` and
  `SET DEFAULT` rows;
- joins those action rows to `PRAGMA table_info` child-column `notnull` and
  `dflt_value` metadata;
- reports blockers when `SET NULL` targets a `NOT NULL` child column, when
  `SET DEFAULT` targets a child column with no explicit default, or when a
  `NOT NULL` child column has a `NULL` default;
- preserves current/next source hashes, pagination, stale-cursor rejection,
  inherited `index_xinfo` and FK diagnostics, and a WordPress postmeta import
  smoke.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next213.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next213.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted next212 child-action lookup index coverage, next209 implicit
  parent primary-key arity, parent UNIQUE/collation/partial diagnostics, and
  accepted PRAGMA optimize/index_xinfo/table-info analysis. The new surface is
  child-column readiness for FK `SET NULL` / `SET DEFAULT` actions.

Dependency closure:

- No new support component is needed. This reuses `SQLitePragmaSchemaCatalog`,
  `PRAGMA foreign_key_list`, `PRAGMA table_info`, and the existing
  current-source cursor helpers.
