# SELECT CTE flatten materialize current-next35

Added `SQLiteSelectCteFlattenMaterializePlan`, a bounded planner surface for
SQLite-style `WITH` common table expressions that classifies each CTE reference
as flattenable or materialized. The planner records `MATERIALIZED` and
`NOT MATERIALIZED` hints, column aliases, recursive keyword state, reference
counts, and materialization blockers such as multiple references, VALUES bodies,
DISTINCT, GROUP BY/HAVING, LIMIT, compound SELECT bodies, aggregates, windows,
and recursive self references.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCteFlattenMaterializeCurrentNext35Test.php
```

Expected dashboard movement after local verification: `+50` focused PASS cases
for `phpPass` from the new lane-scoped test file. This does not change the
mapped upstream denominator because it is a bounded current-source planner
surface, not a newly mapped upstream inventory unit.

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-cte-flatten-materialize-current-next35.php --self-test
```

Non-overlap: this slice does not repeat accepted non-recursive CTE
materialization execution, derived-table materialization, SELECT query
flattening current-next29, JSON table cursor/source/constraint work, SELECT
SQL subqueries, expression ORDER BY, GROUP BY text, VFS/WAL/B-tree storage
clusters, or Unicode GLOB behavior. It is specifically current/next planner
evidence for deciding when CTEs should be flattened versus materialized.

Dependency closure: no new support component is needed. The slice is a
lane-local planner helper over SQL text and reuses existing SELECT SQL parsing
boundaries and row-array execution surfaces for downstream execution.
