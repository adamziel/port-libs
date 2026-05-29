# PRAGMA quick_check index rootpage current-source next135

## Scope

Adds a bounded current-source helper for copied WordPress `wp_options`
expression-index repair preflights that page through `PRAGMA index_xinfo`
metadata and sqlite_schema rootpage diagnostics under `PRAGMA quick_check`.

This deliberately does not repeat the accepted next124 `integrity_check`
surface. The next135 helper forces the quick-check source, annotates rootpage
rows that need deeper integrity follow-up, and keeps the database/catalog/SQL
source cursor stable across paginated repair work.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckIndexRootpageCurrentSourceNextTest.php`
- Result: `1 test files, 67 assertions, 0 failures`, with 61 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-pragma-quickcheck-index-rootpage-current-source-next.php --self-test`
- Result: `wordpress-pragma-quickcheck-index-rootpage-current-source-next self-test passed`

## Non-overlap

Avoids accepted next124 index-rootpage `integrity_check`, next132 PRAGMA
quickcheck/foreign-key/rootpage handling, and accepted JSON/B-tree/WAL/VFS
current-source slices. This patch only adds quick-check-specific
index-rootpage pagination metadata and cursor evidence for the assigned
next135 slice.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded
schema catalog, index_xinfo, SQLite database image, pointer-map, and PRAGMA
integrity helpers already present under `lanes/libsqlite/src`.
