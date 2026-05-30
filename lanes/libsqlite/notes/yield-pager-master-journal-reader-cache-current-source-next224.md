# Pager Master-Journal Reader-Cache Current Source Next224

## Scope

- Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, layered on the accepted next218 master-journal cleanup token gate.
- New behavior: reader-cache pages and read tickets must carry the current pager reader-lease token before reuse after master-journal recovery and cleanup. A page that already passes member-journal, raw master bytes, database file-token, and cleanup-token checks is still reopened if it belongs to a shared-cache reader lease opened before the current source was published.
- Application smoke: `examples/application-pager-master-journal-reader-cache-current-source-next224.php` models copied `wp_options` schema/options pages that remain reusable while an `active_plugins` reader pinned before master-journal cleanup reopens.

## Evidence

- Syntax:
  - `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext224Test.php`
  - `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next224.php`
- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext224Test.php`
- Focused result: `1 test files, 68 assertions, 0 failures`.
- Application smoke command: `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next224.php`
- Application smoke result: `application-pager-master-journal-reader-cache-current-source-next224 self-test passed`.

## Non-Overlap

This slice avoids accepted next218 cleanup-token admission, next212 database file-token admission, raw master-journal bytes, member-token/header/order fences, VFS writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/byte truncation, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is only the reader-lease token fence after master-journal cleanup has already been observed as current.

## Dependency Closure

No new support component is needed. The behavior reuses existing lane-local pager master-journal reader-cache state, current-source tickets, and cleanup-token evidence.
