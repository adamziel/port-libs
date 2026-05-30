# Real Upstream SELECT Aggregate/Limit Dynamic Corpus

Slice: `real-upstream-corpus-select-core-dynamic-20260530T170957Z-0`

Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

Added focused PHP coverage in `SQLiteRealUpstreamSelectAggregateLimitDynamicTest.php`
for hydrated upstream SQLite files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`

Covered upstream scenario ranges:

- `select5-1.0` through `select5-1.3` DISTINCT, grouped aggregate counts, and
  aggregate `ORDER BY`.
- `select5-2.3`, `select5-3.1`, and `select5-4.1` through `select5-4.5`
  HAVING, aggregate rehash, and empty-rowset aggregate behavior.
- `select5-5.11`, `select5-6.1`, `select5-6.2`, `select5-7.2`, and selected
  `select5-8.*` expression `GROUP BY`, NULL grouping, aggregate alias ordering,
  and join/grouped count behavior.
- `select8-1.1` through `select8-1.3` grouped aggregate `LIMIT`/`OFFSET`
  behavior over `GROUP BY LOWER(artist)`.
- Additional dynamic row-slice assertions derived from the exact `select5.test`
  generated `x`/`y` distribution.

Implementation delta:

- `SQLiteSelectSql` now accepts non-identifier `GROUP BY` terms by planning
  them as hidden expression keys.
- `SQLiteSelectQuery` evaluates those hidden expression keys before grouped
  aggregate summarization.

Focused assertion count:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAggregateLimitDynamicTest.php`
- Result: `1 test files, 442 assertions, 0 failures`
- PASS-line delta: `+55` focused PHP PASS cases.

Non-overlap:

- This does not add fake denominator rows or metadata-only admissions.
- It avoids the existing `select1.test` through `select4.test` SELECT core
  corpus coverage and targets the next upstream aggregate/limit scripts.
- It exercises parser-level `SQLiteSelectSql` behavior that previously rejected
  `GROUP BY LOWER(artist)` from `select8.test`.

Exclusions/follow-up:

- Non-aggregate `GROUP BY` rows from `select5-5.2` through `select5-5.5` remain
  excluded because the current grouped executor still requires an aggregate
  value column.
- Ordinal `GROUP BY 1` over qualified source columns from `select5-8.3` remains
  excluded.

Dependency closure:

- No new support component is needed. The slice reuses lane-local
  `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`,
  `SQLiteGroupedAggregate`, and existing scalar function dispatch.
