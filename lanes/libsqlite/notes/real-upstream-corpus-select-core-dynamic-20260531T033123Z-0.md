# real-upstream-corpus-select-core-dynamic-20260531T033123Z-0

Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`.

Added focused real-upstream SELECT corpus coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`.

Owned upstream scenarios:

- `selectB-2.4`: outer `WHERE` and `ORDER BY` over a `UNION ALL` compound
  subquery.
- `selectB-2.8`: ordered compound subquery with outer `LIMIT` and `OFFSET`.
- `selectB-3.2` / `selectB-3.3`: `GROUP BY` and `HAVING` over a compound
  subquery source.
- `selectB-3.4`: ordinary comma join against an aliased compound subquery
  source.
- `selectB-3.7`, `selectB-3.10`, and `selectB-3.14`: `EXCEPT`, `UNION`, and
  `INTERSECT` where one side is a compound subquery.
- `selectB-3.18`: nested `LIMIT` / `OFFSET` compound subquery behavior.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectBCompoundSubqueryDynamicTest.php`.
- 1 citation/source case plus 1,000 dynamic TestRunner PASS cases.
- 29,006 focused behavior assertions.
- Dynamic generic row sets vary the numeric corpus per seed and verify flat
  result values, value counts, and fingerprints across compound subquery
  filtering, grouping, joining, set operations, and nested limit behavior.

Non-overlap:

- This owns the residual `selectB.test` compound-subquery-as-FROM behavior
  cluster.
- It does not repeat accepted `selectC` alias resolution, `selectD`
  parenthesized join/derived aggregate behavior, `selectH` omit-unused
  compound subquery batches, `selectA` merge-order behavior, `selectE` /
  `selectF` compound collation/copy batches, grouped SELECT text, expression
  `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only
  runner rows.
- Mapped denominator remains unchanged because `selectB.test` is already part
  of the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBCompoundSubqueryDynamicTest.php`
  passed: `1 test files, 29006 assertions, 0 failures`.

Expected selected throughput movement:

- PASS lines: `+1001`.
- Assertions: `+29006`.
- Mapped denominator: unchanged, already `1589 / 1589`.

Dependency closure: no new support component is needed; this reuses the
existing native `SQLiteSelectSql` executor and real upstream SQLite test
corpus. No domain-specific source text is introduced.
