# real-upstream-corpus-expression-affinity-dynamic-20260530T203158Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T203158Z-0`

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported sections:
  - `e_expr-14.*`: LIKE wildcard and ESCAPE behavior.
  - `e_expr-16.*`: ASCII case folding for LIKE and escaped pattern bytes.
  - `e_expr-17.*`: GLOB wildcards, NOT GLOB / NOT LIKE negation, and NULL
    propagation.

## Behavior

- Added `SQLiteRealUpstreamExpressionLikeGlobDynamicTest.php`.
- The test compares the bounded PHP `SQLiteSelectSql` expression executor
  against local `sqlite3` for result value and result storage class.
- Dynamic matrix:
  - 25 values x 25 LIKE patterns x positive/negated forms.
  - 25 values x 2 ESCAPE characters x 8 escaped LIKE patterns x
    positive/negated forms.
  - 25 values x 25 GLOB patterns x positive/negated forms.
  - 8 upstream-shaped NULL pattern propagation cases.
- Focused growth: 3309 distinct TestRunner PASS cases / 3311 assertions.

## Non-Overlap

This owns real upstream `e_expr.test` LIKE/GLOB/ESCAPE dynamic behavior. It
does not repeat the accepted expression precedence, unary/remainder, NULL
comparison, BETWEEN precedence, CAST/types2/affinity2/affinity3, Unicode GLOB
range, source-neutral CAST/LIKE/GLOB defaults, SQL expression ORDER BY, JSON,
WAL, VFS, B-tree, or runner evidence surfaces.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionLikeGlobDynamicTest.php`
  - `1 test files, 3311 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteSelectSql` bounded expression executor and the local `sqlite3` oracle
already used by real upstream dynamic corpus tests.
