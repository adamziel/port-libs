# real-upstream-corpus-expression-affinity-dynamic-real-expr2-20260531T034010Z

Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T034010Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Scenario range: `expr-2.1` through `expr-2.28`

Behavior added:

- Added `SQLiteRealUpstreamExpressionAffinityRealExpr2DynamicTest.php` with
  1,200 dynamic REAL expression cases plus one ownership/provenance case.
- The batch widens upstream `expr.test` REAL arithmetic and comparison behavior
  across `+`, `-`, `*`, `/`, `%`, `<`, `<=`, `>`, `>=`, `=`, `==`, and `!=`.
- Each case checks native `SQLiteSelectSql` expression execution against a
  local `sqlite3` oracle for `typeof()`, `quote()`, `IS NULL`, and
  `coalesce()` results.
- Fixed SQLite infinity text/quote formatting so overflowed REAL expression
  results are rendered as `9.0e+999` / `-9.0e+999`, matching SQLite instead of
  PHP `Inf` / `-Inf`.

Focused evidence:

- Red-first: initial focused run produced `1 test files, 6001 assertions, 4
  failures` on overflowed REAL multiplication formatting.
- After fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealExpr2DynamicTest.php`
  passed with `1 test files, 6005 assertions, 0 failures`.
- Focused PASS-line growth: `+1201` TestRunner cases from real upstream
  `expr.test` behavior.

Non-overlap:

- This does not repeat accepted `types2`, `affinity2`, `affinity3`, REAL
  literal unary/modulo behavior, int64 overflow arithmetic `expr-1.200..1.271`,
  NULL comparison `e_expr-8`, REAL cast-prefix, EXISTS projection, operator
  precedence, or modulo-only expression batches.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is
  countable PHP PASS-line growth over already mapped upstream inventory.

Dependency closure:

- No new support component is needed. The slice reuses the native
  `SQLiteSelectSql`, `SQLiteSelectExpression`, and core scalar function
  dispatch paths, with `sqlite3` used only as a focused oracle for real upstream
  expected behavior.

Next task:

- Continue expression-affinity only on non-overlapping upstream expression
  behavior, preferably the remaining known-red unary-plus/IS diagnostic cluster
  or another real upstream expression section that exercises native executor
  semantics rather than metadata rows.
