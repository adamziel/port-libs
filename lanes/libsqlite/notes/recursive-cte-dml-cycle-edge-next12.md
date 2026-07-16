# Recursive CTE DML Cycle Edge Next12

This slice tightens parser-level `WITH RECURSIVE` behavior for cyclic graph
walks. `UNION` recursive CTEs now deduplicate duplicate anchor rows before the
frontier is seeded, matching SQLite's distinct queue behavior and preventing
duplicate roots from leaking into cycle traversal results. `UNION ALL`
continues to preserve duplicate anchors and duplicate bounded recursive rows.

The focused corpus also locks DML rejection for anchor and recursive arms:
`DELETE`, `UPDATE`, and `INSERT` arms remain invalid inside this bounded SELECT
executor instead of being treated as recursive row sources.

Evidence command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteCycleDmlEdgeTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS recursive CTE cycle edge union deduplicates duplicate values anchors
PASS recursive CTE cycle edge union all preserves duplicate values anchors
PASS recursive CTE cycle edge union deduplicates duplicate select anchors
PASS recursive CTE cycle edge union cycle terminates on self edge
PASS recursive CTE cycle edge union cycle terminates on three node loop
PASS recursive CTE cycle edge union cycle can start from duplicate roots
PASS recursive CTE cycle edge union cycle supports outer in predicate
PASS recursive CTE cycle edge union cycle supports exists predicate
PASS recursive CTE cycle edge union cycle supports not exists predicate
PASS recursive CTE cycle edge union cycle can join final rows to wp options
PASS recursive CTE cycle edge union cycle supports computed state columns
PASS recursive CTE cycle edge union cycle supports outer aggregate
PASS recursive CTE cycle edge union cycle supports limit offset
PASS recursive CTE cycle edge union cycle supports named bind root
PASS recursive CTE cycle edge union cycle supports positional bind root
PASS recursive CTE cycle edge union cycle preserves column aliases in plan
PASS recursive CTE cycle edge union cycle can feed ordinary cte
PASS recursive CTE cycle edge union cycle after ordinary cte
PASS recursive CTE cycle edge union cycle supports duplicate edges once
PASS recursive CTE cycle edge union all bounded cycle keeps duplicate edge rows
PASS recursive CTE cycle edge rejects anchor delete arm
PASS recursive CTE cycle edge rejects anchor update arm
PASS recursive CTE cycle edge rejects anchor insert arm
PASS recursive CTE cycle edge rejects recursive delete arm
PASS recursive CTE cycle edge rejects recursive update arm
PASS recursive CTE cycle edge rejects recursive insert arm
PASS recursive CTE cycle edge rejects nested dml cte body
PASS recursive CTE cycle edge rejects malformed cycle dml token

1 test files, 28 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-recursive-cte-cycle-edge.php
```

The smoke traverses copied `wp_options` dependency edges with a cycle and
duplicate root, returning `siteurl`, `home`, `blogname`, and `_transient_feed`
once each.

Non-overlap: this does not repeat accepted recursive current-frontier coverage,
SELECT/JOIN/GROUP BY/subquery/ORDER BY SQL text, JSON table source/cursor/
constraint work, Unicode GLOB, or accepted VFS/WAL/B-tree clusters. It only
adds duplicate-anchor `UNION` cycle behavior plus DML-arm guard coverage.

Dependency closure: no new support component is needed. The slice reuses the
existing SELECT SQL, VALUES, join, predicate, aggregate, bind, and CTE
executors.
