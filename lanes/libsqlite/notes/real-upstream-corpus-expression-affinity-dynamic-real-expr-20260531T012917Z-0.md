# real-upstream-corpus-expression-affinity-dynamic-real-expr-20260531T012917Z-0

Base accepted HEAD: `a890092c734c05eb72a795bdc37321c497f93beb`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-29.2.*` and `e_expr-29.3.*`: `CAST(TEXT AS REAL)` consumes the longest real-number prefix, including leading space.
  - `e_expr-29.4.*`: nonnumeric text casts to `REAL` value `0.0`.
  - `e_expr-32.1.*`: `CAST(TEXT AS NUMERIC)` keeps exact integer prefixes as `INTEGER` and promotes fractional or out-of-range prefixes to `REAL`.

## Change

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealPrefix20260531T012917ZTest.php`.
- The test builds 1,456 sqlite3-oracle-backed cases from 26 prefix-shaped text inputs, `REAL` and `NUMERIC` casts, 4 right-hand numeric expressions, and 7 arithmetic/comparison operators.
- The PHP side exercises `SQLiteSelectSql` expression dispatch and compares `quote(...)`, `typeof(...)`, and `IS NULL` behavior against sqlite3.

## Non-Overlap

This does not repeat accepted expression-affinity shards for `affinity2`, `affinity3`, `types2`, `types3`, CASE/iif affinity, NULL/coalesce, e_expr-12 syntax, BETWEEN, LIKE/GLOB, broad real arithmetic, real-literal syntax, expression `ORDER BY`, grouped SELECT text, JSON, B-tree, WAL, VFS, trigger, date, pragma, or metadata-only suite admission. It owns the upstream `e_expr.test` real/numeric text-prefix conversion behavior through arithmetic/comparison dispatch.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLiteSelectSql` executor and local `sqlite3` only as an oracle for focused test expectations.
