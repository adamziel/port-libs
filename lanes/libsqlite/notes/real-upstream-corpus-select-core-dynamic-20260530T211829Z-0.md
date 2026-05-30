# real-upstream-corpus-select-core-dynamic-20260530T211829Z-0

Added `SQLiteRealUpstreamSelect6DerivedDynamicCorpusTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- `select6-1.1` through `select6-1.5`: derived-table projection, row counts, and nested `DISTINCT` counts.
- `select6-1.6`: grouped aggregate subqueries joined by the group key.
- `select6-1.8`: grouped aggregate subquery aliases joined by alias names.

Focused PHP coverage:

- 1,008 distinct TestRunner PASS cases.
- 4,039 focused behavior assertions.
- Dynamic generic application row sets vary the integer key range over 250 seeds and exercise derived row counts, nested distinct result sets, and two grouped subquery join shapes per seed.

Non-overlap:

- This slice owns the supported `select6.test` FROM-subquery count/distinct/group-join cluster.
- It does not repeat accepted `select1` through `select5`, `select8`, `select9`, `selectA`, `selectB`, `selectC`, or `selectD` batches, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select6.test` is already present in the hydrated upstream runner-map evidence.
- Upstream `select6-1.9` and `select6-3.3` through `select6-3.10` were not admitted in this patch because the current bounded SQL executor rejects bracketed generated column names and single-quoted aggregate aliases in outer derived-table projections. Those remain follow-up executor/parser behavior, not counted here.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6DerivedDynamicCorpusTest.php`
  - Result: `1 test files, 4039 assertions, 0 failures`
  - PASS lines: 1,008

Dependency closure:

- No new support component is needed for the admitted batch. The test reuses existing `SQLiteSelectSql` derived table, distinct, grouping, aggregate, alias, join, ordering, and predicate behavior.
