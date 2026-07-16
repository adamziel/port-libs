# Real Upstream Corpus: Expression Affinity Dynamic Real Oracle

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T210423Z-0`
Base: `6b3b48d963616c004933a32f66ee47ce4ec74885`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-2` REAL arithmetic behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
  - `cast-1` REAL conversion behavior for integer, REAL, text, and BLOB inputs.

## Coverage Added

- Added `SQLiteRealUpstreamExpressionAffinityDynamicRealOracleTest.php`.
- Adds 1000 distinct arithmetic cases plus two guard/application cases:
  - left operand matrix: 25 REAL-affinity terms including integer-to-REAL casts, text numeric-prefix casts, BLOB numeric-prefix casts, nested NUMERIC/INTEGER casts, unary casts, small REALs, and large REALs.
  - right operand matrix: 10 REAL-affinity terms.
  - operators: `+`, `-`, `*`, `/`.
- Each arithmetic case compares native `SQLiteSelectExpression` evaluation against local `sqlite3` for storage class and REAL value tolerance.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealOracleTest.php
1 test files, 5005 assertions, 0 failures
```

PASS-line delta: `+1002`.
Mapped denominator delta: `+0`; this is PASS-line growth over already hydrated upstream files, not a new denominator row.

## Non-Overlap

This does not duplicate accepted `affinity2`, `affinity3`, `types2`, LIKE/GLOB, NULL-logic, or broad cast matrix handoffs. The slice is specifically an oracle-backed REAL arithmetic matrix over dynamic CAST/text/BLOB/unary operands from `expr.test` and `cast.test`.

## Dependency Closure

No new support component is needed. The test reuses the existing native expression evaluator and the already available local `sqlite3` oracle pattern used by adjacent real-upstream corpus tests.
