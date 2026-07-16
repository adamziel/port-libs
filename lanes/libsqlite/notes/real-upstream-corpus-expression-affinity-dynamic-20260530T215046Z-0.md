Micro-slice: real-upstream-corpus-expression-affinity-dynamic-20260530T215046Z-0
Base accepted HEAD: ced5e7f57ac3f6a7cf8403098b6a9b4f0ee89285

Upstream source:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test
- Covered expr2-1.1 through expr2-1.4 nested IS TRUE/FALSE, OR, NOT, and column equality truthiness.

Behavior change:
- Projection expressions now parse `IS TRUE`, `IS NOT TRUE`, `IS FALSE`, and `IS NOT FALSE` as truth-value predicates before generic `IS` comparison parsing.
- This fixes cases such as `SELECT '1' IS TRUE`, which SQLite treats as truthiness instead of equality against integer 1.

Focused corpus:
- Added `SQLiteRealUpstreamExpressionAffinityExpr2TruthDynamicTest.php`.
- The test builds 2,500 upstream-shaped variants across row storage values, comparison literals, left truth terms, and result wrappers.
- Expected `quote()` and `typeof()` results are generated from the local `sqlite3` oracle using a temporary script, then compared against `SQLiteSelectSql`.
- Focused PASS-line growth: 2,501 PASS cases, 5,004 assertions.

Non-overlap:
- This does not repeat the accepted expression NULL comparison, affinity2/types2 comparison matrices, BETWEEN affinity, cast comparison, or affinity3 view/join shards.
- The owned gap is projection-level expr2 truthiness parsing for `IS TRUE/FALSE`, while existing WHERE predicate handling already used the correct truth-value path.

Dependency closure:
- No new support component is needed. The existing `sqlite3` oracle path used by adjacent real-upstream tests is reused for expected output generation.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityExpr2TruthDynamicTest.php` => 1 test files, 5004 assertions, 0 failures.
