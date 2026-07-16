# real-upstream-corpus-expression-affinity-dynamic-20260531T041223Z-0

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T041223Z`
Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`

Added `SQLiteRealUpstreamExpressionAffinityLikeGlobNullDynamic20260531Test.php`
as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-17.2.6` through `e_expr-17.2.9`: NULL propagation for `NOT GLOB`
    and `NOT LIKE` when either operand is NULL.

Behavior:

- Fixes parser-level `LIKE ... ESCAPE ...` expression handling so the right
  pattern is split from the `ESCAPE` operand before expression parsing.
- This lets `NULL LIKE pattern ESCAPE x`, `value LIKE NULL ESCAPE x`, and the
  corresponding `NOT LIKE` forms evaluate to SQLite NULL instead of rejecting
  the expression.
- The new oracle-backed shard widens the upstream NULL propagation behavior
  across LIKE, NOT LIKE, LIKE ESCAPE, NOT LIKE ESCAPE, GLOB, and NOT GLOB.

Focused coverage:

- `1,201` focused TestRunner PASS cases.
- `6,007` focused assertions.
- Expected `phpPass` movement: `2,006,296 -> 2,007,497`.
- Mapped denominator movement: none. The upstream inventory is already mapped;
  this is countable PHP behavior growth over hydrated upstream behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLikeGlobNullDynamic20260531Test.php`
  - `1 test files, 6007 assertions, 0 failures`

Non-overlap:

- This owns the NULL propagation slice of upstream `e_expr-17.2.6..17.2.9`,
  including parser-level `LIKE ... ESCAPE ...` with a NULL operand.
- It does not repeat the accepted non-NULL LIKE/GLOB exact matrix, Unicode
  GLOB range behavior, LIKE/GLOB predicate metadata, expression precedence,
  CASE/iif affinity, casts, `types2`, `types3`, `affinity2`, `affinity3`,
  expression `ORDER BY`, grouped SELECT, JSON, B-tree, WAL, VFS, pager,
  trigger, date, pragma, source-neutral cleanup, or metadata-only suite
  admission.

Dependency closure:

- No new support component is needed. The slice reuses the native
  `SQLiteSelectSql` parser/executor, native LIKE/GLOB matchers, and local
  `sqlite3` only as an oracle for hydrated upstream expected values.
