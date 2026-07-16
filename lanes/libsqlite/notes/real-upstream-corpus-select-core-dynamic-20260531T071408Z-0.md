# real-upstream-corpus-select-core-dynamic-20260531T071408Z-0

Micro-slice: `real-upstream-corpus-select-core-dynamic-20260531T071408Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- Upstream scenarios: `select6-5.1` and `select6-5.2`

Implemented coverage:

- Added `SQLiteRealUpstreamSelect6ComputedDerivedJoinDynamicTest.php`.
- Ports the upstream computed FROM-subquery comma join behavior where the left
  subquery projects `x+3 AS a`, the right subquery projects `x AS b`, and the
  outer query joins on `a=b` with `ORDER BY a`.
- The PHP dynamic corpus generalizes the upstream delta from `3` across 500
  deterministic seeds and checks both the explicitly aliased and unaliased
  derived-source forms, for 1,000 dynamic TestRunner cases plus one source
  citation case.

Non-overlap:

- This owns the previously uncovered `select6-5.1` / `select6-5.2` computed
  derived-subquery join section.
- It does not repeat accepted `select6-1.x`, `select6-3.x`, `select6-8.x`,
  `select6-9.x`, `select6-11.x`, `select5` aggregate/grouping, `select7`
  grouped CASE, selectD/E/F/G/H dynamic batches, expression `ORDER BY`, grouped
  SELECT text, JSON table SELECT source/cursor/constraint work, or any
  metadata-only runner rows.

Dependency closure:

- No new support component is needed. This reuses the native PHP
  `SQLiteSelectSql` derived-table, projection-expression, comma-join,
  outer-WHERE, and ORDER BY execution paths.
