# real-upstream-corpus-expression-affinity-dynamic-20260531T030733Z-0

Added `SQLiteRealUpstreamExpressionAffinityInListDynamic20260531Test.php` as an
additive real upstream expression-affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  `e_expr-12.3.78` through `e_expr-12.3.84`: `IN` and `NOT IN`
  expression-list syntax forms.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  `types2-5.*` and `types2-6.*`: right-hand `IN(...)` values are expression
  values and do not inherit column affinity.

Focused PHP coverage:

- 7,524 dynamic behavior cases plus one ownership/provenance case.
- 15,057 focused assertions.
- Each dynamic case compares native `SQLiteSelectSql` output against a local
  `sqlite3` oracle for `quote(...)`, `typeof(...)`, and SQL NULL status.
- Covered forms include `IN`, `NOT IN`, `IS NULL`, `IS NOT NULL`, CASE
  dispatch, and numeric coercion guards across NULL, integer, real,
  numeric-looking text, leading-zero text, BLOB, empty, and negative-text
  inputs.

Non-overlap:

- This does not repeat accepted cast-prefix, row-context arithmetic, unbound
  parameter, NULL comparison, syntax-diagram, `types2` row-filter, BETWEEN,
  collation-affinity, real-literal, or overflow-arithmetic files.
- The narrower surface is dynamic expression-list `IN`/`NOT IN` result
  semantics through parser-level `SQLiteSelectSql` projection dispatch.
- Negative numeric CAST-left and bare negative numeric left operands exposed a
  separate expression-splitting/quote edge and are intentionally left for a
  parser follow-up rather than weakening this green corpus batch.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListDynamic20260531Test.php`
  - `1 test files, 15057 assertions, 0 failures`

Expected dashboard movement:

- `phpPass +7525` focused TestRunner PASS lines.
- Mapped denominator coverage remains `1589 / 1589`; this is real PHP
  behavior growth over already mapped upstream inventory.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLiteSelectSql` expression parser/evaluator, scalar `quote()`/`typeof()`,
  CASE dispatch, and `IN`/`NOT IN` expression-list behavior.
