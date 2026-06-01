# real-upstream-corpus-pager-wal-dynamic-20260601T180726Z-0

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Ported scenarios: `pager1-8.1` through `pager1-8.2`
- Upstream behavior: special filenames `:memory:` and empty string open isolated transient databases. A second handle using the same special filename does not see table `x1`, and a transaction that inserts `William` and `Anne` rolls back to the original `Charles`, `James`, `Mary` rows.

## Implementation

- Added `SQLiteRealPagerBoundaryPlan::transientSpecialFilenameRows()` with 1000 deterministic, source-neutral pager rows across memory and temporary special filenames plus page-size variation.
- Added `SQLiteRealUpstreamCorpusPagerWalTransientFilenameDynamic20260601T180726ZTest.php` with source-citation checks, 1000 focused behavior cases, inventory/non-overlap assertions, and malformed-input coverage.
- Updated `lane-status.json` from `phpPass` `6158827` to `6159830` for the +1003 new TestRunner PASS cases. Mapped coverage remains `1589 / 1589`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalTransientFilenameDynamic20260601T180726ZTest.php`
  - Result: `1 test files, 24015 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDeleteJournalMissingDynamic20260601T162216ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T020744ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T095120ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T103759ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1InvalidPageDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1RollbackMaxPageDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPeerLockJournalCleanupDynamic20260601T143633ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalTransientFilenameDynamic20260601T180726ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalZeroPageSizeJournalDynamic20260601T122933ZTest.php`
  - Result: `9 test files, 223309 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
  - Result: no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalTransientFilenameDynamic20260601T180726ZTest.php`
  - Result: no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 7 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output

## Non-Overlap

This slice targets only `pager1.test` `pager1-8.*` transient special-filename behavior. It avoids accepted pager1 max-page, invalid-page, DBMOVED, peer-lock cleanup, zero page-size journal fallback, missing DELETE-journal commit failure, VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation, savepoint2, `walro`, `walsetlk`, and `pager2` savepoint churn.

## Dependency Closure

No new support component is needed. The slice reuses source-neutral pager boundary modeling and the hydrated upstream `pager1.test` source file.

## Next

Continue pager/WAL corpus work only on a non-overlapping upstream section or on one of the broad full-lane blockers. Good remaining targets are broader pager/VFS transaction application, WAL durability edge cases, or the 16 current broad failures.
