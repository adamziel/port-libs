# real-upstream-corpus-select-core-dynamic-20260530T193722Z-0

Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`.

Added focused real-upstream SELECT aggregate coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`.

Covered upstream scenarios:

- `select5-1.0` through `select5-1.3`: DISTINCT, aggregate `GROUP BY`, aggregate `ORDER BY`, and aggregate-first projection.
- `select5-2.3`: `HAVING count(*)` filtering, expanded across dynamic LIMIT/OFFSET variants.
- `select5-3.1`: grouped aggregate results after bounded `WHERE` filtering.
- `select5-4.2`: empty aggregate `count(x)` result behavior.
- `select5-5.2`, `select5-5.3`, `select5-5.4`, and `select5-5.11`: grouped non-aggregate projection and grouped expression projection.
- `select5-6.1` and `select5-7.2`: NULL grouping behavior and grouped `count(*)` / `count(x)` ordering.
- `select5-8.1`, `select5-8.2`, `select5-8.5`, and dynamic `select5-8.x`: aggregate counts over comma joins and rowid predicates.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect5AggregateDynamicCorpusTest.php`
- Result: `1 test files, 6336 assertions, 0 failures`
- Selected PASS-line growth: `+1056` distinct focused TestRunner cases.

Non-overlap:

- This slice targets `select5.test` aggregate, HAVING, NULL grouping, and
  comma-join aggregate behavior.
- It avoids already accepted `select4`, `select7`, `select8`, `select9`,
  `selectA`, `selectB`, compound-collation, JSON table source/cursor, expression
  ORDER BY, grouped SELECT text, and subquery coverage.
- Upstream `select5-5.5` is intentionally excluded because SQLite's source
  comment marks that query family as non-standard SQL, while the PHP executor
  correctly rejects selecting a non-grouped column missing from the grouped row.

Dependency closure:

- No new support component is needed. This reuses the existing
  `SQLiteSelectSql` row-array SELECT executor, aggregate grouping, predicate,
  ORDER BY, LIMIT/OFFSET, and comma-join support.
