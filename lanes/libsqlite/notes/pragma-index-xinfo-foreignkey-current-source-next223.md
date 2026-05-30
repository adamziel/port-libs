# PRAGMA index_xinfo / foreign-key current-source next223

This slice adds current/next PRAGMA evidence for `PRAGMA foreign_key_list`
`match` column behavior. SQLite records `MATCH name` in the catalog, but its
native enforcement still uses the built-in match semantics; copied Application
import preflights should flag custom names such as `MATCH FULL` before treating
them as alternate FK enforcement.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` composes the accepted
  next218 `index_xinfo` / `foreign_key_list` current-source page.
- Adds deterministic `foreign_key_match_clause` rows grouped by FK id and
  sequence, marking `NONE` and `SIMPLE` as default semantics and other match
  names as `custom_match_name`.
- Current/next source IDs include match summaries, so resume cursors reject
  stale pages when copied schema reparses remove or change custom match names.
- Application smoke coverage models a copied `wp_postmeta_import` schema that
  removes `MATCH FULL` before admitting a parent-key repair.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next223.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next223.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

Avoids accepted PRAGMA parent-key coverage, rowid alias, omitted parent-column
arity, collation/order/partial-index diagnostics, child nullability, SET
DEFAULT action checks, child action indexes, and RESTRICT timing. This patch
only adds `foreign_key_list.match` current-source diagnostics.

Dependency closure:

No new support component is needed. The slice reuses `SQLitePragmaSchemaCatalog`
foreign-key parsing, existing `SQLiteSchemaRecord` metadata, and the accepted
current-source PRAGMA pagination chain.
