# Pager Master-Journal Reader Cache Current Source Next252

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a narrow pager/master-journal reader-cache fence for recovered master-journal member manifests. It composes the accepted next248 page-owner map admission and then rejects otherwise-current reader-cache entries or read tickets when their `master_member_manifest_token` predates the recovered current source.

WordPress relevance: copied `wp_options` imports that recover an attached-database master journal can keep schema/options cache pages only when the read ticket is stamped with the current member manifest. Stale member-manifest readers reopen before plugin/settings import resumes.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext252Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next252.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext252Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next252.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next252 self-test passed`

## Non-Overlap

This slice does not repeat next248 page-owner map admission, rootpage map, statement snapshot, shared generation, schema reparse, read transaction, cleanup token, page-image receipt, WAL checkpoint/savepoint, rollback-journal commit/apply, VFS writer/lock/sync, B-tree, JSON, SQL executor, or encoding behavior. It adds only the recovered master-member manifest fence after those existing cache-source checks pass.

## Dependency Closure

No new support component is needed; this reuses lane-local pager master-journal reader-cache fences and current-source cache tickets.
