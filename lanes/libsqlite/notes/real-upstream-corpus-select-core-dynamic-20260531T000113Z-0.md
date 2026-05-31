# real-upstream-corpus-select-core-dynamic-20260531T000113Z-0

Implemented an additive real upstream SELECT core corpus batch from hydrated
SQLite source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`
- Scenarios: `selectA-2.1`, `selectA-2.1.1`, `selectA-2.1.2`,
  `selectA-2.2`, `selectA-2.4`, `selectA-2.5`, `selectA-2.6`,
  `selectA-2.10`, and `selectA-2.11`.
- Behavior: compound `UNION ALL` ordering with result-column names,
  qualified left-arm result columns, qualified `ORDER BY` terms, mixed
  storage-class ordering, explicit `NOCASE` and `BINARY` collations, reversed
  compound arms, and whole-compound `LIMIT` / `OFFSET` windows.

Added `SQLiteRealUpstreamSelectAUnionOrderDynamicTest.php` with 1,099 distinct
TestRunner cases and 8,779 focused assertions. This is PASS-line growth only;
mapped denominator coverage remains unchanged because `selectA.test` is
already present in the hydrated upstream inventory.

Non-overlap:

- Existing select-core batches cover `selectA.test` later `INTERSECT` /
  `EXCEPT` rows (`selectA-2.41` and later), plus separate `selectB` through
  `selectH`, `limit.test`, and `subselect.test` slices.
- This batch owns the earlier `selectA.test` `UNION ALL` merge-order section
  with dynamic result windows and does not add metadata-only runner rows,
  generated fake upstream script ids, or domain-specific API names.

Exclusion:

- A red probe found `selectA-2.7` and `selectA-2.9` default `c COLLATE NOCASE`
  ordering still diverge in the current compound executor. Those rows are not
  admitted here and should be fixed in a later SELECT collation slice before
  adding them as passing corpus coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionOrderDynamicTest.php`
  passed with `1 test files, 8779 assertions, 0 failures`.
- Dependency closure: no new support component is needed; this reuses the
  lane-local `SQLiteSelectSql` compound SELECT executor, result-column
  ordering, collation handling, and final LIMIT/OFFSET trimming.
