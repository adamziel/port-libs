# real-upstream-corpus-expression-affinity-dynamic-20260531T023054Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicScalarSubquery20260531T023054ZTest.php` as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-35.1.*`: scalar subquery expressions are accepted.
- `e_expr-36.3.*`: scalar subquery value is the first returned row, with `ORDER BY` affecting that row.
- `e_expr-36.4.*`: scalar subquery value is SQL NULL when the subquery returns no rows.

Focused coverage:

- 1,000 dynamic TestRunner PASS cases plus one ownership/provenance case.
- Dynamic matrix: 10 scalar subquery projections x 5 WHERE filters x 10 ORDER BY variants x 2 wrappers.
- Behavior exercised through native `SQLiteSelectSql` over generic `app_expr_source` rows with INTEGER, REAL, NUMERIC, and TEXT affinity-shaped columns.
- Expected values come from a local `sqlite3` oracle generated from the same real upstream behavior family.

Non-overlap:

- This does not repeat accepted `types2` comparison/subquery affinity files, `affinity2`/`affinity3` comparison and REAL join files, CASE base-affinity coverage, real-prefix cast coverage, scalar IN-list expression coverage, SELECT SQL subquery WHERE predicates, expression ORDER BY, grouped SELECT text, JSON table source/cursor/constraint work, or metadata-only runner rows.
- This batch owns scalar subquery expressions in SELECT projection positions, especially first-row/empty-subquery behavior from `e_expr.test`.

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteSelectSql` scalar subquery, projection expression, ordering, affinity coercion, `quote()`, and `typeof()` behavior.

Expected dashboard movement:

- `phpPass +1001` from focused real upstream PHP TestRunner cases.
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth over already mapped real upstream expression inventory.
