# Pager Master Journal Reader Cache Current Source Next591-606

This slice extends the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` directly after merged next591-606 with next591 through next606 statement VDBE branch reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for AutoCommitBranch, SavepointBranch, CheckpointBranch, JournalModeBranch, VacuumBranch, IncrVacuumBranch, ExpireBranch, TableLockBranch, VBeginBranch, VCreateBranch, VDestroyBranch, VOpenBranch, VFilterBranch, VColumnBranch, VNextBranch, and VRenameBranch state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext590Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next591-606.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext590Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next591-606.php`

Non-overlap: builds directly on accepted next591-606 statement VDBE arithmetic, affinity, compare, jump, and first branch fences. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, or prior VDBE behavior.

Source class note: extended the established canonical source class for this domain; no new numbered source class was needed.

Next slice: continue pager master-journal reader-cache current-source statement VDBE virtual-table and page-count opcode fences after next606.
