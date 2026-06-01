# real-upstream-corpus-pager-wal-dynamic-20260601T194301Z-0

Base accepted HEAD: `717fdb296ffb612f8a5e6c844680b41c0b18437c`.

## Upstream Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Ported scenario: `pager1-30.1`
- Upstream behavior: opening an empty database in `PRAGMA locking_mode=EXCLUSIVE` with a stale `test.db-journal` deletes the journal without rollback, returns `SELECT count(*) FROM sqlite_master` as `0`, and keeps the main database lock status at `reserved` while temp remains `closed`.

## Patch

- Added `SQLiteRealPagerBoundaryPlan::exclusiveEmptyDatabaseJournalCleanupRows(1000)` with source-neutral dynamic rows for pager1-30.1.
- Added `SQLiteRealUpstreamCorpusPagerWalEmptyDatabaseJournalDynamic20260601T194301ZTest.php` with 1000 dynamic behavior cases plus hydrated-source, inventory, non-overlap, dependency-closure, and invalid-count guards.
- Updated lane status to record the focused PASS-case delta for this current-base upstream corpus slice.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalEmptyDatabaseJournalDynamic20260601T194301ZTest.php` -> no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalEmptyDatabaseJournalDynamic20260601T194301ZTest.php` -> `1 test files, 27021 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 8 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed with no output.
- No example smoke was added for this internal pager boundary.

## Non-Overlap

This targets `pager1.test` `pager1-30.1` empty-database stale-journal cleanup only. It avoids accepted pager1 transient filenames, invalid page requests, peer-lock cleanup, zero page-size journal fallback, missing DELETE journal unlink failure, max-page rollback, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, and savepoint2 WAL signature coverage.

## Dependency Closure

No new support component is needed. The patch reuses the source-neutral real pager boundary model and the hydrated upstream `pager1.test` source truth.
