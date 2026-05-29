# PRAGMA Index Quickcheck Foreign Key Current Source Next141

## Behavior

Adds `SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNext`, a current/next
cursor for copied WordPress SQLite import repairs that combines:

- `PRAGMA index_list(table)` and per-index `index_xinfo` / rootpage integrity rows.
- table-scoped `PRAGMA quick_check(table)` rootpage blockers.
- `PRAGMA foreign_key_check(table)` blocker rows.
- source identity over current and next database bytes, schema rows, catalog rows,
  index PRAGMA SQL, quick-check SQL, and foreign-key SQL.

The cursor reports whether the next source is ready, which blocker families remain,
and whether the repair cleared all current index-root, quick-check-root, and
foreign-key blockers. Stale continuation cursors are rejected when the database,
schema, catalog, SQL text, or offset changes.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNextTest.php`
- Result: `1 test files, 74 assertions, 0 failures`
- PASS-line delta for this lane patch: `+61`

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-pragma-index-quickcheck-foreignkey-current-source-next.php`
- Result: `wordpress-pragma-index-quickcheck-foreignkey-current-source-next self-test passed`

## Non-Overlap

This slice avoids accepted PRAGMA quick_check/index_xinfo/rootpage-only and
foreign_key_check-only cursors by requiring the combined index catalog,
quick-check, and foreign-key current/next source identity in one resumable repair
cursor. It does not touch accepted WAL, B-tree page relocation, JSON table,
VFS lock/write/sync, grouped SELECT, expression ORDER BY, or range-cost planner
surfaces.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
SQLite catalog, PRAGMA index integrity, quick-check root integrity, and
foreign-key-check components.
