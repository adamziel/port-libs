# Real Upstream Corpus: Expression REAL Arithmetic

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T221527Z-0`

Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`

Added focused corpus coverage in `SQLiteRealUpstreamExpressionRealArithmeticDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Scenario range: `expr-2.1` through `expr-2.28`
- Covered behavior: REAL arithmetic, REAL/NUMERIC cast operands, comparison operators, modulo, division-by-zero NULL results, and NULL propagation.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionRealArithmeticDynamicTest.php`
- Result: `1 test files, 14767 assertions, 0 failures`
- PASS cases: `3080` newly selected TestRunner PASS cases

Non-overlap:

- This is separate from the existing `cast.test`/`types3.test` real expression batch and `affinity2.test` column-comparison batch.
- It does not repeat accepted LIKE/GLOB, NULL-logic, expression ORDER BY, SELECT text dispatch, or source-neutral API work.

Exclusions:

- `expr-2.26` and `expr-2.26b` overflow multiplied by zero currently diverge from sqlite3 and are left for a separate behavior fix.
- Modulo with `1e300` operands is excluded because the current PHP implementation warns while converting unrepresentable floats to integers.

Dependency closure:

- No new support component is needed; this reuses the existing `sqlite3` oracle pattern for real upstream corpus tests and the lane-local `SQLiteSelectSql` expression evaluator.
