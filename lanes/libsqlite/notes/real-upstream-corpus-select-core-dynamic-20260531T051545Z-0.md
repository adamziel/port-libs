# real-upstream-corpus-select-core-dynamic-20260531T051545Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T051545Z-0`
Base accepted HEAD: `597c96169f44cb49bb577675ba5900812102b596`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- Ported section: `selectB-3.1` through `selectB-3.16`, representing the same
  behavior family repeated by upstream for query-flattener/index-control modes
  through `selectB-6.16`.
- Behavior cluster: `DISTINCT`, `GROUP BY`, `HAVING`, `EXCEPT`, `UNION`, and
  `INTERSECT` over compound SELECT subqueries in `FROM` or compound arms.

## Local coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectBSetOpsDynamicTest.php`.
- The file contributes 1002 distinct TestRunner PASS cases: one upstream source
  citation case, 1000 dynamic behavior cases, and one dependency-closure case.
- Each dynamic case executes `SQLiteSelectSql` against generic in-memory
  application tables and verifies full flattened rows, result count, first/last
  boundaries, and result fingerprints.

## Non-overlap

This owns the `selectB.test` early set-operation subquery cluster. It does not
repeat accepted `selectB` derived compound flattening (`selectB-2.*`),
postfix `NOT NULL` arithmetic (`selectB-*.25`), nested LIMIT/OFFSET and
arithmetic JOIN rows (`selectB-*.17` through `selectB-*.24`), grouped SELECT
text, expression `ORDER BY`, SELECT subqueries, JSON table source/cursor/
constraint work, WAL/VFS/B-tree slices, or metadata-only runner rows.

## Dependency closure

No new support component is needed. The slice reuses lane-local `SQLiteSelectSql`
compound SELECT, derived-table, grouping, aggregate count, DISTINCT, and set-op
execution over generic SQLite application rows.
