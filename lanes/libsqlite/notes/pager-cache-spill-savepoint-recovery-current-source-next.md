# Pager Cache-Spill Savepoint Recovery Current Source Next120

This slice adds a bounded pager behavior for dirty cache pages that spill to the database while a savepoint is open, then are undone by `ROLLBACK TO`.

The behavior is intentionally narrower than the accepted master-journal cache-spill/current-source next114 work. Next114 verifies that cache-spill inputs come from the recovered current source. This slice verifies the follow-up recovery invariant: spilled dirty bytes do not survive when the savepoint has first page images for those pages.

Verification target:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNext120Test.php`
- `php lanes/libsqlite/examples/application-pager-cache-spill-savepoint-recovery-current-source-next120.php`

Dependency closure: no new support component is needed. The slice reuses `SQLiteSavepointStack` page-image rollback and `SQLitePagerDirtyPageCacheSpillPlan`.
