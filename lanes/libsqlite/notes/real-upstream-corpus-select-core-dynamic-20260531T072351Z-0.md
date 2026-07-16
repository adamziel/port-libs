# real-upstream-corpus-select-core-dynamic-20260531T072351Z-0

Added `SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php`, a real upstream
SELECT corpus batch based on hydrated SQLite
`/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`.

## Upstream Source

- `select9.test` compound SELECT `ORDER BY` / `LIMIT` / `OFFSET` generator.
- Cited source markers:
  - `test_compound_select`
  - `SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 1`
  - `SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 2`
  - generated `LIMIT $iLimit` and `OFFSET $iOffset` clauses.

## Behavior Ported

- Six upstream-shaped compound SELECT forms over generic application rows:
  - `UNION ALL` natural compound row order.
  - `UNION ALL ORDER BY 1`.
  - `UNION ALL ORDER BY 2, 1` with nullable text ordering.
  - `UNION ORDER BY 1, 2` with duplicate elimination.
  - `UNION ORDER BY 2` with nullable text ordering.
  - `INTERSECT ORDER BY 1`.
- 1,000 dynamic LIMIT/OFFSET cases slice the expected compound result set in
  the same style as upstream `test_compound_select`.
- Assertions check exact flattened result values, count, first/last edge
  values, and a JSON fingerprint.

## Non-Overlap

This owns the residual `select9.test` compound SELECT limit/offset generator
surface. It avoids the accepted `select1` repeated wildcard/derived/correlated
batches, `select2` / `select5` dynamic batches, `select7` grouped/correlated
coverage, `select8` grouped aggregate LIMIT/OFFSET, `selectA` / `selectB`
compound batches, `selectC` alias resolution, `selectD` parenthesized joins,
`selectE` / `selectF` compound collation/copy behavior, `selectG` VALUES, and
`selectH` wide/omit-unused compound coverage.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php`
  - `1 test files, 7008 assertions, 0 failures`
  - 1,002 focused PASS lines.

## Dependency Closure

No new support component is needed. The batch reuses the existing native
`SQLiteSelectSql` compound SELECT executor and the hydrated upstream SQLite
test corpus as source truth.
