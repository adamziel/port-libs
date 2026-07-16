## Pager Master-Journal Reader Cache VDBE Branch-Condition Fence

This consolidation keeps the accepted final VDBE branch-condition handoff reader-cache behavior inside the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, but removes the final generated numbered method and direct test/example names for that range. The public entry point is now `currentSourceVdbeSavepointBranchConditionFence()`, with descriptive predecessor methods for NotNull, Ne, Eq, Gt, Le, Lt, Ge, ElseEq, ZeroOrNull, SeekHit, IfNotOpen, NotOpen, IfOpen, Transaction, and AutoCommit branch-condition fences.

No new support component is needed; this reuses the existing canonical pager master-journal reader-cache plan and migrates direct callers away from final generated method/file names.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeBranchConditionFenceTest.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-vdbe-branch-condition-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeBranchConditionFenceTest.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-vdbe-branch-condition-fence.php --self-test`
- `git diff --check -- lanes/libsqlite`
