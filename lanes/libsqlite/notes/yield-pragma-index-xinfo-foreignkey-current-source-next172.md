# PRAGMA index_xinfo foreign_key_check target current-source next172

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current/next
  yield helper for `PRAGMA index_xinfo` combined with target-scoped
  `PRAGMA foreign_key_check(table)`.
- The source cursor now includes normalized `foreign_key_check` SQL and the
  resolved target table, so a resume token from `foreign_key_check(wp_options)`
  cannot be reused for `foreign_key_check(wp_posts)`.
- Parent-index admission and FK violation rows are filtered to the requested
  child table while index_xinfo rows remain available for the selected index.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next172.php --self-test`
- PHP lint and `git diff --check -- lanes/libsqlite` were run before handoff.

## Non-overlap

This slice avoids accepted next158/159/165/166 PRAGMA index_xinfo/FK work:
current-source pagination, implicit parent primary-key columns, FK action/MATCH
metadata, and deferrability summaries. The new behavior is target-scoped
`foreign_key_check(table)` filtering and target-aware source/cursor identity.

## Dependency Closure

No new support component is needed. The patch reuses the existing
`SQLitePragmaForeignKeyCheck`, `SQLitePragmaSchemaCatalog`, and index_xinfo
yield helpers.
