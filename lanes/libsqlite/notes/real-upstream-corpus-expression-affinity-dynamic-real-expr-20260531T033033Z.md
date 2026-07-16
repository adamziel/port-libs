# Real Upstream Corpus: Expression Affinity Dynamic REAL Overflow

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T033033Z`
Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T033033Z-0`
Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Ported sections:
  - `expr-1.200` through `expr-1.271`: int64-boundary `+`, `-`, and `*` overflow promotion to REAL.
  - `expr-13.2` through `expr-13.7`: string-to-integer and string-to-REAL conversion at the int64 boundary.

## PHP Coverage Added

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicOverflow20260531T033033ZTest.php`.
- The test uses local `sqlite3` only as an oracle generator, then verifies `SQLiteSelectSql` execution for the same dynamic expressions.
- Matrix: `25` left operands x `8` right operands x `5` operators = `1000` expression cases, plus one ownership/source guard.
- Focused result: `1 test files, 4009 assertions, 0 failures`.
- PASS-line movement for this focused file: `1001` new PASS cases.

## Non-Overlap

This does not repeat the accepted `affinity3` REAL join/view arithmetic,
`cast.test` REAL/NUMERIC arithmetic, `e_expr-12.3` syntax diagram, current-time
literal SELECT SQL, or IS/unary-plus diagnostic clusters. This slice targets
`expr.test` overflow promotion and int64 string conversion only.

## Dependency Closure

No new support component is needed. The existing `SQLiteSelectSql` expression
executor and local `sqlite3` oracle pattern are reused for focused upstream
parity evidence.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicOverflow20260531T033033ZTest.php`
- Pending final handoff checks: PHP lint for changed PHP files and `git diff --check -- lanes/libsqlite`.
