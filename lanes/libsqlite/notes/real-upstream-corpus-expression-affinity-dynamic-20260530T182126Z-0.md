# real-upstream-corpus-expression-affinity-dynamic-20260530T182126Z-0

Added `SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php` as a high-yield
real upstream expression/affinity corpus slice.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-1.1..1.4` arithmetic
  - `expr-1.22..1.23` precedence
  - `expr-1.38..1.44` unary and bitwise operators
  - `expr-1.56` remainder
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
  - `cast-1.39`, `cast-1.45`, `cast-1.49`, `cast-1.62`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-100..300` insert affinity and comparison rules

Focused growth:

- `1001` distinct TestRunner PASS cases in the new focused file.
- `1001` behavior assertions.
- No mapped denominator movement claimed.

Non-overlap:

- This does not repeat the accepted expression concat, e_expr precedence bulk,
  prior `types2.test` indexed affinity slice, date affinity, CAST/LIKE/GLOB
  source-neutral cleanup, SQL expression ORDER BY, or VDBE numeric overflow
  corpus.
- The new surface is dynamic row-array SELECT execution over arithmetic,
  precedence, unary/bitwise, remainder, cast affinity, and numeric text/REAL
  affinity rows using generic `app_expr_affinity` data.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php`
  - `1 test files, 1001 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLiteSelectSql` expression evaluator and cast/affinity behavior.
