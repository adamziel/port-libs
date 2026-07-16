# yield-sqlite-pragma-integrity-autoindex-pointermap-root-current-next55

- Behavior: extends `SQLitePragmaIntegrityAutoindexYield` rows with root-page cursor metadata (`previous_rootpage`, `current_rootpage`, `next_rootpage`), b-tree page type, largest-root flag, and auto-vacuum pointer-map entry details for `sqlite_autoindex_*` roots.
- Application path: copied `wp_options` import preflight can stream UNIQUE autoindex integrity rows in current/next pages while preserving pointer-map root ownership evidence for auto-vacuum databases.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexPointerMapRootCurrentNext55Test.php` passed with 59 PASS lines, 80 assertions, 0 failures.
- Example evidence: `php lanes/libsqlite/examples/application-pragma-integrity-autoindex-pointermap-root-current-next55.php` emits first/second page JSON with pointer-map root metadata.
- Non-overlap: avoids accepted batch50 autoindex pagination-only coverage by adding per-row pointer-map/root cursor metadata; avoids accepted B-tree root-collapse/page-move, overflow freelist, JSON, WAL, VFS writer/lock/sync, SQL subquery/order/grouped text, and Unicode GLOB clusters.
- Dependency closure: no new support component required; reuses existing native `SQLiteDatabase`, `SQLitePointerMapEntry`, b-tree page assembly, and PRAGMA integrity parser helpers.
