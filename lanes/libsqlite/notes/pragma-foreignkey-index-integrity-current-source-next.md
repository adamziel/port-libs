# PRAGMA Foreign-Key Index Integrity Current Source Next131

- Added `SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext`, a current-source cursor wrapper around the existing foreign-key parent-index integrity collector.
- The slice keeps yielded PRAGMA integrity/foreign-key pages bound to source ids plus record, foreign-key, table-row, and SQL hashes, so resumed pages reject stale cursors after schema/data/source changes.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNextTest.php` passed with `65` PASS lines, `71` assertions, and `0` failures.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-pragma-foreignkey-index-integrity-current-source-next.php` reports the copied `wp_options` parent-key admission blocker and the resumable source id.
- Non-overlap: avoids accepted PRAGMA index rootpage quickcheck cursors, PRAGMA foreign-key partial-root pagination, quickcheck/stat FK handling, and earlier autoindex/foreign-key preflight surfaces; this patch only adds current-source cursor stability for the combined parent-index integrity scan.
- Dependency closure: no new support component is needed; this reuses existing native PHP schema catalog, FK check, and PRAGMA integrity helpers.
