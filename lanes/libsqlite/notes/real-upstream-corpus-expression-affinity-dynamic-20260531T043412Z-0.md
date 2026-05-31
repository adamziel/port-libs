# real-upstream-corpus-expression-affinity-dynamic-20260531T043412Z-0

Base accepted HEAD: `7db59d242cf2590641e3217c1b87d71727256c92`.

Added `SQLiteRealUpstreamExpressionAffinityRangeMembershipDynamicTest.php`, a sqlite3-oracle-backed dynamic shard for upstream SQLite expression affinity behavior.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-1.86` through `expr-1.95`: `BETWEEN` / `NOT BETWEEN` and NULL bounds.
  - `expr-1.111` through `expr-1.118`: `IS`, `IS NOT`, and distinct-from spellings.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - `types2-5.*`: `IN (...)` membership behavior for numeric-looking text values and NULL.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRangeMembershipDynamicTest.php`
- Result: `1 test files, 7688 assertions, 0 failures`.
- PASS-line delta if accepted: `+1921`, moving `phpPass` from `2098495` to `2100416`.

Non-overlap:

- This shard does not repeat accepted real-affinity3, real-IN, real-expr2, cast matrix, or overflow arithmetic batches. It focuses on scalar SELECT expression range/membership/null-distinct truth tables over integer, REAL, TEXT numeric-looking, leading-zero, empty-text, and NULL literals.

Dependency closure:

- No new support component is needed. The shard reuses the existing bounded `SQLiteSelectSql` parser/executor and the local `sqlite3` oracle used by adjacent real-upstream expression-affinity dynamic tests.
