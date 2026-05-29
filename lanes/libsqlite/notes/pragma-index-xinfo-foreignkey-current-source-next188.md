# PRAGMA index_xinfo foreign-key current-source next188

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current/next
diagnostic layer for `PRAGMA index_xinfo` plus foreign-key catalog checks. The
slice identifies FK parent keys whose column list is backed only by a partial
UNIQUE index. SQLite does not admit partial UNIQUE indexes as parent-key
evidence for FK enforcement, even when the `index_xinfo` key columns match.

The WordPress smoke models copied `wp_options` plugin metadata where
`wp_options(plugin_slug, locale)` references `wp_plugin_slugs(slug, locale)`.
The current schema only has `CREATE UNIQUE INDEX ... WHERE active = 1`; the
next schema adds a full UNIQUE index and clears the partial-parent blocker.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 62 assertions, 0 failures`
  - 54 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next188.php --self-test`
  - WordPress smoke passed

## Non-Overlap

This avoids accepted next181 parent collation rows, next184 parent sort rows,
and next185 NULL child-key omission behavior. It adds a distinct parent-key
admission rule: matching partial UNIQUE indexes are reported separately from
full UNIQUE parent keys and keep next-state blocked until a full UNIQUE parent
index exists.

## Dependency Closure

No new support component is required. The slice reuses existing
`SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and current-source cursor
hashing primitives.
