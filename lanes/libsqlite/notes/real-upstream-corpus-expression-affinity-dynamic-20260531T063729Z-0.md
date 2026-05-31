# real-upstream-corpus-expression-affinity-dynamic-20260531T063729Z-0

Added `SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531Test.php`
with real upstream SQLite expression-affinity coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`.

Upstream source sections:

- `e_expr.test` `e_expr-9.*`
- Behavior: postfix `COLLATE` binding, comparison collation override,
  grouped boolean collation no-op behavior, and `BETWEEN` collation
  propagation.

Focused behavior:

- `15,744` oracle-backed dynamic TestRunner cases compare `SQLiteSelectSql`
  output with local `sqlite3` over `NOCASE`, `BINARY`, and `RTRIM`
  collations.
- The matrix intentionally uses generic SQLite text values only and adds no
  domain-specific APIs or fixtures.

Non-overlap:

- This does not repeat accepted `expr.test` REAL arithmetic, `e_expr` unary or
  binary operator families, cast-prefix conversion, NULL comparison operators,
  expression syntax templates, `types2` affinity matrices, expression
  `ORDER BY`, SELECT subqueries, BETWEEN precedence, or Unicode GLOB ranges.
  It owns the `e_expr-9.*` COLLATE binding branch for this session.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The slice reuses local `sqlite3` as an
  oracle for real upstream corpus expectations and the existing bounded
  `SQLiteSelectSql` executor plus native collation comparison behavior.
