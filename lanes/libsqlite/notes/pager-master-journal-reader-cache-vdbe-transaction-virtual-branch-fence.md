# Pager Master Journal Reader Cache VDBE Transaction Virtual Branch Fence

This consolidation keeps the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` behavior for statement VDBE transaction and virtual-table branch reader-cache fences while removing the individual numbered production wrappers. Reader-cache reuse after master-journal recovery still requires current-source tokens for AutoCommitBranch, SavepointBranch, CheckpointBranch, JournalModeBranch, VacuumBranch, IncrVacuumBranch, ExpireBranch, TableLockBranch, VBeginBranch, VCreateBranch, VDestroyBranch, VOpenBranch, VFilterBranch, VColumnBranch, VNextBranch, and VRenameBranch state through `currentSourceVdbeTransactionVirtualBranchFence()`.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeTransactionVirtualBranchFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-transaction-virtual-branch-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeTransactionVirtualBranchFenceTest.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-transaction-virtual-branch-fence.php`

Non-overlap: consolidates the already accepted statement VDBE transaction and virtual-table branch fence surface without changing WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, or earlier reader-cache behavior.

Source class note: kept the established canonical source class for this domain; no new numbered source class or compatibility shim was added.

Next slice: continue removing remaining numbered pager master-journal reader-cache production wrappers.
