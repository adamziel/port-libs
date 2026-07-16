# pager-cache-spill-wal-recovery-current-source-next135

Implemented a current-source pager/WAL recovery slice for cache-spill admission.

- Behavior: `SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan` composes WAL transaction recovery with WAL-mode dirty-page cache-spill routing. It admits spill frames only after the committed WAL prefix is selected as the current source, rejects cache pages sourced from discarded uncommitted tails, and records reader/corrupt-tail reasons that defer WAL reset.
- Application smoke: `examples/application-pager-cache-spill-wal-recovery-current-source-next135.php` models a copied `wp_options` retry import where committed WAL pages become the spill source and an uncommitted transient tail is excluded.
- Non-overlap: avoids accepted pager master-journal cache-spill next132, hot-journal cache-spill next127, WAL checkpoint reader hot-journal next132, WAL savepoint byte truncation, rollback-journal apply/commit, VFS writer/sync/lock clusters, and WAL checkpoint transaction planning.
- Dependency closure: no new support component needed; this reuses native `SQLiteWal::transactionRecoveryBoundary()` and `SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext()`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillWalRecoveryCurrentSourceNext135Test.php`
- `php lanes/libsqlite/examples/application-pager-cache-spill-wal-recovery-current-source-next135.php`
- `php -l lanes/libsqlite/src/SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerCacheSpillWalRecoveryCurrentSourceNext135Test.php`
- `php -l lanes/libsqlite/examples/application-pager-cache-spill-wal-recovery-current-source-next135.php`
- `git diff --check -- lanes/libsqlite`
