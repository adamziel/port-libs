# PRAGMA index_list integrity rootpage current-source next143

## Behavior

- Adds `SQLitePragmaIndexListIntegrityRootpageCurrentSourceNext`, a current/next pager for `PRAGMA index_list` rows plus sqlite_schema rootpage integrity rows for the target table and listed indexes.
- The current source can show a pointer-map/rootpage blocker while the next source proves the repaired image clears it; both current and next database/catalog/SQL hashes are included in the resume source id.
- Cursor resume rejects stale next database bytes, stale next catalog metadata, stale SQL, stale integrity SQL, and stale offsets.

## WordPress path

Copied `wp_options` imports often rebuild partial and auto indexes after cleanup. The smoke keeps the copied index repair resumable only when `PRAGMA index_list(wp_options)` metadata and the repaired rootpage integrity view match the same current/next source images.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexListIntegrityRootpageCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-list-integrity-rootpage-current-source-next.php --self-test`

## Non-overlap

This does not repeat accepted `next139` single-source `index_list`/rootpage integrity pagination. It layers current-vs-next repair delta and resume-source validation for the paired current and repaired images, avoiding accepted FK, pointer-map, quickcheck, and index_xinfo surfaces.

## Dependency closure

No new support component is needed; the slice reuses existing native PHP schema catalog, b-tree page, pointer-map, and PRAGMA integrity helpers.
