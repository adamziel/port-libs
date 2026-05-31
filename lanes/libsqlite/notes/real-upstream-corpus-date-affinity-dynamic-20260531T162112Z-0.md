# real-upstream-corpus-date-affinity-dynamic-20260531T162112Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- Scenarios: `affinity3-200`, `affinity3-210`, `affinity3-220`, `affinity3-250`, `affinity3-260`
- Behavior ported: a UNION-derived `idmap`/materialized `mzed` table has no declared affinity for `id`, so `JOIN ... USING(id)` does not coerce integer `1` from the mapping side to text `'1'` from the data side. Only the same-storage text id row joins, matching upstream's `4 xyz e` result shape with automatic indexes both enabled and disabled.

## Implementation

- `SQLiteSelectSql::usingPredicate()` now routes JOIN USING equality through `SQLiteAffinityComparison` with optional per-column affinity metadata instead of PHP's broad numeric equality.
- The default no-affinity path now preserves storage-class-sensitive comparison, which is the relevant upstream `affinity3.test` UNION behavior.
- Existing row metadata keys are inspected when present, but the dynamic test uses generic source-neutral row arrays to exercise the normal SELECT/JOIN path.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicAffinity3UnionIdmap20260531T162112ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicAffinity3UnionIdmap20260531T162112ZTest.php` -> `1 test files, 32016 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3RealJoinDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInSelectDynamic20260531T065544ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4AggregateJoinDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T043457ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicRealSelect20260531T031726ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelect1Test.php` -> `6 test files, 23617 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

## Non-overlap

This slice avoids the saturated date4 corpus noted by the earlier date-affinity blocker and ports a distinct affinity3 UNION/JOIN USING storage-class behavior. It does not change mapped denominator coverage because `1589 / 1589` is already mapped, and it does not add metadata-only admission rows.

## Dependency closure

No new support component is needed. The slice reuses existing `SQLiteSelectSql`, `SQLiteAffinityComparison`, and TestRunner infrastructure.
