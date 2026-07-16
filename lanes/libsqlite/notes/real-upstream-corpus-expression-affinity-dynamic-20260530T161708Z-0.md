# Real upstream corpus expression affinity dynamic

Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T161708Z-0`

Status: behavior-backed parser/executor growth from hydrated upstream SQLite
`test/e_expr.test`.

Ported upstream scenarios:

- `e_expr-1.2.1` through `e_expr-1.6`: comparison, equality, and LIKE
  precedence in SELECT projection expressions.
- `e_expr-2.1` through `e_expr-2.4`: unary prefix operators.
- `e_expr-3.1` through `e_expr-3.6`: unary plus is a no-op over text,
  integer, real, blob, and NULL values.
- `e_expr-4.1` through `e_expr-4.4`: `=`/`==` and `!=`/`<>` spelling parity.
- `e_expr-5.1` through `e_expr-6.5`: concatenation and integer-remainder
  behavior.
- `e_expr-8.1.1` through `e_expr-8.1.16` plus the full `e_expr-8.2`
  13-by-13 literal matrix: `IS` / `IS NOT` null and storage-class behavior.
- `e_expr-10.1.1` through `e_expr-10.2.4`: literal storage classes and
  exponent real literals.

Focused assertion count: `728` assertions in `221` PHP TestRunner PASS cases.
Expected `phpPass` movement: `188377 -> 188598`. Mapped upstream denominator
coverage is unchanged because this is real PHP behavior growth over already
hydrated expression corpus source rather than a new denominator admission row.

Implementation notes:

- Adds parser-level predicate expressions as first-class SELECT projection and
  nested expression operands, reusing the existing native predicate evaluator.
- Preserves SQLite's lower comparison precedence for equality/LIKE/IS relative
  to relational comparisons.
- Keeps unary plus as a no-op over text/blob values and admits exponent real
  literals in SELECT SQL expressions.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  - `1 test files, 728 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the
native PHP SELECT parser, projection, expression, predicate, scalar
`typeof()`/`quote()`, LIKE/GLOB matching, blob value, and numeric conversion
helpers.

Non-overlap: avoids accepted expression ORDER BY, grouped SELECT, subquery,
JSON table, WAL/VFS, B-tree, Unicode GLOB, and domain-neutral API
cleanup surfaces. The narrower surface is real upstream expression/affinity
behavior in SELECT projection and nested predicate-expression positions.
