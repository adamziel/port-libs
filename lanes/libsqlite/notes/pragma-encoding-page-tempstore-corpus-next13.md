# PRAGMA Encoding/Page/Temp Store Corpus Next13

This slice adds bounded native PHP coverage for upstream-style `PRAGMA encoding`, `PRAGMA page_size`, `PRAGMA page_count`, and `PRAGMA temp_store` connection state.

Behavior covered:

- Query and assignment parsing for bare, schema-qualified, equals, parenthesized, quoted encoding, and trailing-semicolon PRAGMA SQL.
- `encoding` normalization for UTF-8, UTF-16, UTF-16le, UTF-16be, numeric aliases, temp-schema no-op handling, and post-schema-change ignore semantics.
- `page_size` power-of-two bounds, empty-database assignment, post-schema-created no-op behavior, temp-schema no-op behavior, and read-only `page_count`.
- `temp_store` DEFAULT/FILE/MEMORY keyword and numeric assignment with schema isolation.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaEncodingPageTempStoreCorpusTest.php`: `1 test files, 68 assertions, 0 failures` with 68 PASS lines.
- `php lanes/libsqlite/examples/application-pragma-encoding-page-tempstore.php`: printed copied `wp_options` pragma preflight JSON with UTF-16le encoding, 8192-byte page size, 18 pages, MEMORY temp_store, and dependency tags.
- `php -l lanes/libsqlite/src/SQLitePragmaEncodingPageTempStoreState.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePragmaEncodingPageTempStoreCorpusTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-pragma-encoding-page-tempstore.php`: no syntax errors.

Non-overlap: avoids accepted PRAGMA locking-mode, synchronous/journal-mode, integrity/quick_check, schema PRAGMA catalog, VACUUM page_size/auto_vacuum planning, VFS writer/sync/rollback/super-journal, WAL byte truncation/checkpoint, B-tree page move/root-collapse/overflow freelist, JSON table, Unicode GLOB, SELECT SQL subquery/grouping/comma-LIMIT, and batch8/9 corpus clusters.

Dependency closure: no new support component is needed; the work reuses lane-local PRAGMA parsing/state conventions and does not require ext/sqlite, hydrated upstream runners, or live-service provider tests.
