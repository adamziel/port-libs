# real-upstream-corpus-pager-wal-dynamic-20260601T162216Z-0

Base accepted HEAD: `7e5889a228115ead961c432c28514d2ac6db2dc1`.

## Upstream Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Ported scenario: `pager1-33.1..33.2`
- Upstream behavior: in `journal_mode=DELETE`, if `test.db-journal` is renamed away before `COMMIT` and the final unlink returns `ENOENT`, COMMIT fails with `disk I/O error`. Restoring the journal and reading the table leaves only the original rows (`one two`).

## Patch

- Added `SQLiteRollbackJournalCommitPlan::commit(..., $deleteMustExist = false)` so DELETE journal commit plans can require an existing delete target only for strict pager boundaries.
- Added strict `require_exists` handling to `SQLiteVfsFileWriter::applyOperations()` for delete operations. Existing delete operations still silently tolerate already-missing files unless the flag is set.
- Routed `SQLiteVfsFileWriter::applyRollbackJournalCommit(..., $deleteMustExist = false)` through atomic apply when strict delete existence is requested.
- Added `SQLiteRealPagerBoundaryPlan::deleteJournalMissingCommitRows(1000)` with source-neutral rows for the pager1-33 missing-unlink boundary.
- Added `SQLiteRealUpstreamCorpusPagerWalDeleteJournalMissingDynamic20260601T162216ZTest.php` with 1000 dynamic behavior cases plus source/inventory guards. Each dynamic case verifies the strict delete operation, the default non-strict compatibility path, and atomic restoration of the original database page after the missing-delete failure.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRollbackJournalCommitPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php` -> no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDeleteJournalMissingDynamic20260601T162216ZTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDeleteJournalMissingDynamic20260601T162216ZTest.php` -> `1 test files, 46019 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 7 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` -> `1 test files, 9489 assertions, 16 failures`; this matches the current lane-status broad blocker. The rollback-journal commit cases in that file passed.

## Non-Overlap

This targets `pager1.test` `pager1-33.1..33.2` DELETE-mode missing-journal unlink failure. It does not repeat accepted pager1 peer-lock cleanup, zero page-size journal fallback, max-page rollback, invalid-page handling, successful rollback-journal commit/apply, VFS rollback apply, sync/apply, lock-state, WAL byte truncation, savepoint2, or wal.test prefix coverage.

## Dependency Closure

No new support component is needed. The patch reuses source-neutral rollback-journal commit planning and the existing VFS atomic operation snapshot/restore path, adding only a strict delete-target existence option needed by the upstream pager boundary.
