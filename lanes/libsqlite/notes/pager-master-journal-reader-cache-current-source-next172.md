# Pager Master-Journal Reader Cache Current Source Next172

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext172Plan`.

Behavior covered:

- Reads the current master journal membership after recovery and derives the attached database paths from the member rollback journals.
- Admits reader-cache pages only when the cache slot database path, entry database path, source token, epoch, master-journal digest, and page digest all match the current attached database source.
- Invalidates cross-database cache-slot bleed, removed attached databases, dirty/pinned pages, stale source tokens, stale epochs, stale master digests, and stale page images.
- Routes next reads through retained cache pages only for the same attached database member; otherwise it reopens from the current source.

Verification:

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext172Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next172.php`

Dependency closure: no new support component is required. The slice reuses bounded PHP planner arrays and existing master-journal path conventions.

Non-overlap: this does not repeat next166 generation/schema/page-count fences, next165 header-generation fences, cache-spill journal modes, master-journal savepoints, hot rollback, VFS writer/sync/lock behavior, WAL checkpoint/savepoint behavior, or B-tree freeblock/freelist behavior.
