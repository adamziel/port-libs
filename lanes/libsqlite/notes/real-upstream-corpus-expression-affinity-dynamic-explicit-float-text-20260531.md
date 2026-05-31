# real-upstream-corpus-expression-affinity-dynamic-20260531T044255Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T044255Z-0`

Added `SQLiteRealUpstreamExpressionAffinityDynamicExplicitFloatText20260531Test.php`
as an additive real upstream expression-affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Sections `expr-13.6` and `expr-13.7`: explicit floating-point string
  operands use String-to-REAL conversion during numeric arithmetic.

Coverage added:

- 250 generated decimal/exponent string spellings derived from the upstream
  explicit-float conversion behavior.
- 5 arithmetic contexts per spelling: left/right `+0`, `*1`, `/1`, and `-0`.
- 1,250 dynamic sqlite3-oracle comparisons plus 1 ownership/source-range test.
- Focused result: `1 test files, 6256 assertions, 0 failures`, with 1,251
  TestRunner PASS lines.

Non-overlap:

- This does not repeat the accepted single max-int `expr-13.6`/`expr-13.7`
  literal checks, integer-boundary literal classification, broad REAL
  arithmetic `expr-2`, REAL `IN` affinity, `types2`, `types3`, `affinity2`,
  `affinity3`, expression ORDER BY, BETWEEN, LIKE/GLOB, CASE/truth, or JSON/WAL/
  B-tree/VFS clusters.
- The owned behavior is parser-level `SQLiteSelectSql` numeric arithmetic over
  explicit floating-point TEXT operands, compared to sqlite3 oracle output.

Dependency closure:

- No new support component is needed. The shard reuses the existing
  parser-level SELECT executor, scalar numeric expression evaluation, and local
  `sqlite3` oracle pattern already used by adjacent real upstream expression
  affinity tests.
