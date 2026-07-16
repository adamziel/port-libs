# Pager master-journal reader cache member-digest fence

Status: production suffix cleanup for the existing pager master-journal reader-cache member-digest fence.

This slice is consolidated into `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCurrentMasterJournalReaderCacheRebase()`. It extends the current-source reader-cache admission boundary with a master-journal member digest fence: a reader-cache page can have the same page image, source id, and epoch as the recovered database image, but it is still invalidated when it was keyed from stale master-journal membership.

Application smoke: `application-pager-master-journal-reader-cache-member-digest-fence.php` covers a copied `wp_options` recovery where a schema reader-cache page still matches the recovered bytes but carries the old master-journal member digest, so the next read misses cache and the next `active_plugins` write journals from the recovered current source.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMemberDigestFenceTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMemberDigestFenceTest.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-member-digest-fence.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-member-digest-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMemberDigestFenceTest.php`
  - `1 test files, 92 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLitePagerMasterJournalReaderCache*Test.php' | sort)`
  - `149 test files, 9983 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-member-digest-fence.php`
  - `application-pager-master-journal-reader-cache-member-digest-fence self-test passed`

Dashboard delta: none. This is consolidation-only suffix cleanup that preserves the existing 92 focused assertions and does not change `phpPass` or mapped upstream coverage.

Non-overlap: this avoids accepted pager master-journal reader-cache next159, master-journal cache recovery next122, master-journal hot cache next136, savepoint/master-journal reader/cache slices, rollback-journal commit/apply, super-journal commits, WAL byte truncation/checkpoint/savepoint clusters, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is specifically stale master-journal member-digest invalidation for reader-cache pages before the next pager source is used.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal current-source and bounded page-image cache primitives.

Next task: continue with broader pager/VFS transaction application or a release-runner blocker; avoid another master-journal cache wrapper unless it adds a distinct file-handle, digest, or upstream-runner blocker.
