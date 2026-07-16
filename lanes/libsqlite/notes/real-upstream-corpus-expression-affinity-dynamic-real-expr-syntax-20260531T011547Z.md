# Real Upstream Expression Affinity Dynamic Real Expr Syntax

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T011547Z-0`

Source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`

Ported upstream behavior:

- `e_expr.test` section `e_expr-12.3`, expression syntax diagram execution.
- The PHP corpus uses the upstream `tblname(cname)` shape with generic rows and
  compares `SQLiteSelectSql` results against a local `sqlite3` oracle.
- Dynamic matrix: 38 upstream expression forms, 4 column/REAL substitutions, 16
  row values, for 2,432 focused TestRunner PASS cases.

Excluded from this slice:

- `LIKE ... ESCAPE`, `ISNULL`, `NOTNULL`, `NOT NULL`, custom `CAST` target names,
  `IN`/`NOT IN` list syntax, `substr()`, concat, and modulo forms that currently
  expose parser or coercion gaps in the PHP SELECT expression executor.

Dependency closure:

- Reuses the existing bounded `sqlite3` CLI oracle dependency already used by
  nearby real-upstream corpus tests. No new support component is needed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprSyntaxTest.php`
  - `1 test files, 7303 assertions, 0 failures`
