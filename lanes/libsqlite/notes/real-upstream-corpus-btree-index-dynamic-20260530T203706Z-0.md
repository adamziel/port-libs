# real-upstream-corpus-btree-index-dynamic-20260530T203706Z-0

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

## Upstream Source

- Hydrated upstream SQLite checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test`
- Source file: `test/indexA.test`
- Ported scenario range: `indexA-1.1` through `indexA-1.7` and `indexA-4.1` through `indexA-8.1`

## Coverage Added

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexAJoinPlannerGuardCases()`.
- Added `SQLiteRealUpstreamBtreeIndexAJoinPlannerDynamicTest.php`.
- Focused PASS cases: `720`
- Focused behavior assertions: `10296`
- Expected `phpPass` movement: `612306 -> 613026`
- Mapped coverage: unchanged at `1472 / 1589`

This is non-overlapping with the existing accepted `indexA.test` coverage for sections `2.1` and `3.1`, which already lives in `indexAPartialAffinityMatrixCases()`. This slice covers partial-index join routing, aggregate covering partial-index scans, collation admission/reopen behavior, bloom-filter partial joins, `INDEXED BY` partial predicates, and expression/partial-index coexistence from separate upstream sections.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexAJoinPlannerDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexAJoinPlannerDynamicTest.php`
  - Result: `1 test files, 10296 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded B-tree/index corpus plan helpers and the hydrated upstream SQLite `.test` source as behavior truth.
