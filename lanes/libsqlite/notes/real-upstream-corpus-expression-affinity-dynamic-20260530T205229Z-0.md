# real-upstream-corpus-expression-affinity-dynamic-20260530T205229Z-0

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

Added focused real-upstream expression LIKE/ESCAPE coverage from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-14.1` through `e_expr-14.7`: LIKE wildcard direction, percent and
  underscore matching, ASCII case-folding, and ESCAPE literalization.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionLikeEscapeDynamicTest.php`
- Result: `1 test files, 2942 assertions, 0 failures`
- New focused PASS lines: `2938`

Non-overlap:

- This does not repeat the accepted `types2.test`, `affinity2.test`,
  `affinity3.test`, expression NULL comparison, BETWEEN matrix, expression
  precedence/operator corpus, date affinity, B-tree numeric affinity, Unicode
  GLOB range, or source-neutral CAST/LIKE/GLOB cleanup batches.
- The owned gap is the real upstream `e_expr.test` `e_expr-14.*`
  LIKE/ESCAPE predicate matrix executed through `SQLiteSelectSql` and checked
  against the local `sqlite3` oracle.

Dependency closure:

- No new support component is needed. The batch reuses the existing native PHP
  SELECT expression evaluator, LIKE matcher, ESCAPE handling, and `quote()`
  projection behavior.
