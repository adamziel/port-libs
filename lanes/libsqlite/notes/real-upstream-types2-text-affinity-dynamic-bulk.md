# Real Upstream Types2 Text Affinity Dynamic Bulk

- Session: `port-dev-sqlite-yield-dyn-real-expr-20260530T202353Z`
- Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Upstream sections: `types2-2.*`, `types2-3.*`, and `types2-4.*` comparison-affinity families, specifically the TEXT-affinity follow-up not covered by the existing INTEGER/NUMERIC/no-affinity bulk shard.

## Coverage

Added `SQLiteRealUpstreamTypes2TextAffinityDynamicBulkTest.php` with 100 generated TEXT-affinity predicates across `=`, `==`, `<`, `<=`, `>`, `>=`, `!=`, `<>`, `IS`, and `IS NOT` against numeric and text literal spellings. Each predicate is checked in 10 contexts: rowids, negated rowids, projection truth vector, `IS 1`, `IS NOT 1`, count, negated count, min rowid, max rowid, and ordered text vector.

Focused result: `1 test files, 1004 assertions, 0 failures`, with 1001 TestRunner PASS cases.

## Non-Overlap

This is distinct from `SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`, which explicitly covers INTEGER, NUMERIC, and no-affinity columns and leaves TEXT-affinity comparisons to a follow-up because row-array SELECT execution does not carry declared column affinity metadata. This shard exercises the shared affinity comparison primitive directly against a hydrated `sqlite3` oracle.

## Dependency Closure

No new support component is needed. The test reuses the existing bounded `SQLiteRealExpressionAffinityCorpusPlan` and local `sqlite3` oracle used by adjacent real upstream expression/affinity tests.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTypes2TextAffinityDynamicBulkTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTypes2TextAffinityDynamicBulkTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
