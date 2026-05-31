# Real Upstream Corpus: Expression Affinity Dynamic Bitwise Real Expr

- Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T012508Z`
- Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T012508Z-0`
- Base accepted HEAD: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
    - `expr-1.*` division, modulo, bitwise, shift, boolean, and concatenation expression families.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
    - `cast-1.*` and `cast-2.*` target conversion behavior.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`
    - storage-class observation for expression outputs.
- Added focused PHP coverage:
  - `SQLiteRealUpstreamExpressionAffinityDynamicBitwiseRealExprTest.php`
  - `4608` dynamic TestRunner PASS cases plus one corpus-count guard.
  - `18438` assertions, `0` failures in focused verification.
- Non-overlap:
  - Existing `SQLiteRealUpstreamExpressionAffinityDynamicRealExprTest.php`
    covers `+`, `-`, `*`, comparisons, `IS`, and `IS NOT`.
  - This handoff owns `/`, `%`, `&`, `|`, `<<`, `>>`, `AND`, `OR`, and `||`
    across the same real-affinity cast matrix.
  - It does not repeat accepted expression CASE affinity behavior, expression
    real precision/index/null/coalesce batches, SELECT expression `ORDER BY`,
    or SQLite API source-neutral cleanup.
- Dependency closure:
  - No new support component is needed. The test reuses the lane-local
    `SQLiteSelectSql` expression executor, scalar `quote()` / `typeof()`
    dispatch, and the local `sqlite3` oracle used for real upstream corpus
    parity checks.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicBitwiseRealExprTest.php`
    passed: `1 test files, 18438 assertions, 0 failures`.
