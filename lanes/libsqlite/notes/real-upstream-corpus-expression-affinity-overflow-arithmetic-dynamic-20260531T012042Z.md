# real-upstream-corpus-expression-affinity-dynamic-20260531T012042Z-0

Accepted base: `9c01a66e5dc81444d443e06defaf90851a98b56e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Scenarios: `expr-1.200` through `expr-1.271`

Patch summary:

- Added `SQLiteRealUpstreamExpressionAffinityOverflowArithmeticDynamicTest.php`.
- The test ports the upstream int64 overflow arithmetic cluster into a 35 x 35 x 3 dynamic matrix over `+`, `-`, and `*`.
- Each case compares native `SQLiteSelectSql` output with a local `sqlite3` oracle for storage class, NULL/sign predicates, and numeric value parity. REAL quote formatting is compared by numeric tolerance because SQLite and the PHP formatter render equivalent large REAL values with different decimal lengths.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityOverflowArithmeticDynamicTest.php`
- Result: `1 test files, 25730 assertions, 0 failures`
- PASS-line delta: `+3676`

Non-overlap:

- This slice does not repeat accepted expression-affinity batches for `affinity2` unary coercion/real precision, `types2`, `types3`, CAST target affinity, host parameters, NULL/coalesce, signed literals, BETWEEN/CASE, real-index drift, LIKE/GLOB, expression `ORDER BY`, grouped SELECT, JSON, VFS/WAL/B-tree, or source-neutral API cleanup.
- It owns `expr.test` overflow arithmetic promotion for parser-level SELECT expressions only.

Dashboard movement:

- `phpPass`: `1473407 -> 1477083`
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteSelectSql` executor and local `sqlite3` oracle pattern used by adjacent real upstream expression tests.
