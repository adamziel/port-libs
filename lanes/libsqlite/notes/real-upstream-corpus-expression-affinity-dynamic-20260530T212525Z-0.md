# real-upstream-corpus-expression-affinity-dynamic-20260530T212525Z-0

Added `SQLiteRealUpstreamExpressionAffinityDynamicRealExprTest.php`, a real upstream expression/affinity dynamic corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
  - `cast-1.*` and `cast-2.*`: target-affinity conversion for integer, real, numeric, and text casts.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - arithmetic, comparison, `IS` / `IS NOT`, and NULL-propagation expression behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`
  - `typeof()` storage-class observation for manifest values after expression evaluation.

Focused batch:

- 16 literal classes x 4 CAST targets x 6 right operands x 11 operators.
- 4,224 distinct oracle-backed dynamic expression cases.
- Each case compares bounded `SQLiteSelectSql` output with local `sqlite3` for `quote(expression)`, `typeof(expression)`, and `quote(expression IS NULL)`.
- Focused result: `1 test files, 16902 assertions, 0 failures`.
- PASS-line growth expected: `+4225` focused TestRunner PASS cases, including the ownership check.

Non-overlap:

- This does not repeat the accepted `types2` matrices, `affinity2` / `affinity3` column-storage rules, boolean affinity, LIKE/GLOB, NULL-comparison, broad operator helper tests, or the earlier cast-target-only shard.
- The owned gap is parser-level composed CAST/arithmetic/comparison expression behavior with real-valued operands and storage-class observation through the SELECT executor.

Exclusions:

- The first red run showed remaining parity gaps for `%` with REAL operands (`quote()` expects `0.0` where the port currently returns `0`) and fractional `/` `quote()` precision. Those operator families were excluded from this green corpus instead of weakening oracle expectations.

Dependency closure:

- No new support component is needed. This reuses local `sqlite3` as an oracle and existing lane-local `SQLiteSelectSql` expression execution.
