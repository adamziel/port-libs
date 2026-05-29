# Pager Master-Journal Reader-Cache Current Source Next229

## Scope

- Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, layered on the accepted next224 reader-lease gate.
- New behavior: reader-cache pages and read tickets must carry the current pager cache source token before reuse after master-journal recovery. A page that already passes member-journal, raw master bytes, database file-token, cleanup-token, and reader-lease checks is still reopened if it belongs to a pager cache source minted before the current master-journal recovery publication.
- WordPress smoke: `examples/wordpress-pager-master-journal-reader-cache-current-source-next229.php` models copied `wp_options` schema/options pages that remain reusable while an `active_plugins` reader from the previous pager cache source reopens.

## Evidence

- Syntax:
  - `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext229Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next229.php`
- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext229Test.php`
- Focused result: `1 test files, 65 assertions, 0 failures`.
- WordPress smoke command: `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next229.php`
- WordPress smoke result: `wordpress-pager-master-journal-reader-cache-current-source-next229 self-test passed`.

## Non-Overlap

This slice avoids accepted next224 reader-lease admission, next218 cleanup-token admission, next212 database file-token admission, raw master-journal bytes, member-token/header/order fences, VFS writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/byte truncation, super-journal commit, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is only the pager cache source token fence after the reader lease has already been observed as current.

## Dependency Closure

No new support component is needed. The behavior reuses existing lane-local pager master-journal reader-cache state, current-source tickets, cleanup-token evidence, and reader-lease evidence.
