# Pager Master-Journal Reader-Cache Current Source Next218

## Scope

- Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, layered on the accepted next212 pager reader-cache source ticket.
- New behavior: reader-cache pages and pending read tickets must carry the current master-journal cleanup token before they can be reused after master-journal recovery. A page that otherwise passes database file-token, member-header, member-order, raw master bytes, and recovered-page checks is fenced if it was cached before durable master-journal cleanup/deletion.
- Application smoke: `examples/application-pager-master-journal-reader-cache-current-source-next218.php` models a copied `wp_options` database where schema/options cache readers remain reusable after cleanup, while an `active_plugins` reader pinned before master-journal cleanup reopens.

## Evidence

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext218Test.php`
- Result: `1 test files, 62 assertions, 0 failures`.
- Application smoke command: `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next218.php`
- Result: `application-pager-master-journal-reader-cache-current-source-next218 self-test passed`.

## Non-Overlap

This slice avoids accepted rollback-journal apply, super-journal commit, VFS writer/sync/lock, WAL byte truncation/checkpoint, next211 recovered-page digest, and next212 database file-token fences. It adds only the post-recovery master-journal cleanup token admission gate for current-source reader-cache reuse.

## Dependency Closure

No new support component is needed. The behavior reuses existing lane-local pager master-journal reader-cache state, current-source reader tickets, and VFS cleanup-token evidence.
