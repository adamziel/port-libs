# Pager master-journal reader cache current-source next167

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next167`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext167Plan`, a bounded pager planner for the edge where master-journal recovery has advanced the current source and deleted/retired the master journal file. The next reader cache ticket now includes the master-journal generation and deleted-present state, so a page image that still matches cannot be reused from a stale master generation. Clean current-generation pages are retained, clean image-mismatched pages are refreshed from current source bytes, and dirty/pinned/stale-generation entries force a reader reopen.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next167.php` models a copied `wp_options` database after master-journal deletion. It keeps a shared schema cache page, refreshes a stale options page, and reopens an `active_plugins` reader whose ticket belongs to the previous master-journal generation.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext167Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext167Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next167.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext167Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next167.php`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 85 assertions, 0 failures`, all 85 PASS lines newly added in this lane.

Expected dashboard delta: `phpPass` increases by 85 focused PASS lines. Mapped upstream coverage is unchanged; this is focused pager/master-journal reader-cache behavior over existing mapped pager inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted pager master-journal reader-cache next158/160/161/162/164 membership, header, and reader-reopen clusters, hot-journal statement/savepoint cache handling, cache-spill, WAL checkpoint/savepoint/read-mark behavior, VFS writer/sync/lock work, B-tree, JSON, SQL planner, PRAGMA, trigger, and encoding surfaces. The new behavior is specifically master-journal deleted-generation ticket validation for the next reader cache after recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal membership and reader-cache source ticket primitives.

Next: apply this generation-ticket rule to durable pager/VFS transaction state only if a later slice needs real file-handle current-source writes; avoid another standalone reader-cache wrapper without new pager state.
