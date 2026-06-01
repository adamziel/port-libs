# real-upstream-corpus-pager-wal-dynamic-20260601T085822Z-0

## Scope

- Lane: libsqlite
- Accepted base: d7a19889d5388512c58bffdd0bf40a928a255617
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Upstream scenarios: `pager1-18.2`, `pager1-18.3.1`, `pager1-18.3.2`, `pager1-18.3.3`, `pager1-18.3.4`, `pager1-18.4`, `pager1-18.5`, `pager1-18.6`

## Behavior Ported

This patch adds a real upstream corpus model for pager invalid b-tree page requests:

- rootpage changed to the locking page reports `database disk image is malformed`;
- corrupt zero overflow next-page pointers allow metadata-only `typeof()`/`length()` paths but fail once payload content is loaded;
- corrupt high overflow next-page pointers fail as malformed;
- `ALTER TABLE ... RENAME` with an invalid rootpage keeps the schema parseable but later table access fails as malformed;
- zero child page numbers in interior cells fail when row content is read.

The dynamic test file contributes 1003 focused TestRunner PASS cases and 21520 assertions from this corpus slice.

## Non-Overlap

This is disjoint from accepted WAL checkpoint/savepoint, rollback-journal apply/commit, VFS file writer/sync/lock, pager max-page rollback, B-tree page move/root-collapse/overflow-freelist, JSON table cursor/source/constraint, and SELECT SQL text/group/order/subquery work. It covers the pager1 invalid page request boundary only.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1InvalidPageDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1InvalidPageDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1InvalidPageDynamic20260601Test.php`
  - `1 test files, 21520 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T020744ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T095120ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T103759ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1RollbackMaxPageDynamic20260601Test.php`
  - `4 test files, 64718 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`

- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency Closure

No new support component is needed. The patch reuses the existing `SQLiteRealPagerBoundaryPlan` corpus-plan helper and the hydrated upstream SQLite test checkout for source citation.
