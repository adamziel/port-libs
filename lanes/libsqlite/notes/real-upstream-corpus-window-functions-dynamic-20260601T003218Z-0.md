# real-upstream-corpus-window-functions-dynamic-20260601T003218Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260601T003218Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`
- Sections `4.1`, `4.2`, and `4.3`.

Behavior admitted:

- Named `WINDOW` definitions inside a CTE are expanded only when referenced by a window function.
- `Row_Number() OVER (win)` with `WINDOW win AS (PARTITION BY ...)` keeps independent row-number counters per partition inside the CTE result.
- A referenced named window with an unknown partition column is rejected.
- An unused named window with an unknown partition column is not resolved and does not block a constant `SELECT`, matching upstream `windowB.test 4.3`.

Focused evidence:

- Command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBNamedCteDynamic20260601Test.php`
- Result: `1 test files, 1802 assertions, 0 failures`.
- PASS cases added: `1002` distinct TestRunner cases.

Non-overlap:

- This owns only upstream `windowB.test` named-window CTE validation sections `4.1-4.3`.
- It avoids accepted `windowB` RANGE peer behavior, JSON object inverse behavior, filtered JSON inverse behavior, JSON table/source/constraint work, grouped SELECT text, expression ORDER BY, WAL/VFS/B-tree/planner/PRAGMA/trigger clusters, and metadata-only runner rows.
- No WordPress-specific or numbered production API is introduced.

Dependency closure:

- No new support component is needed; this reuses lane-local `SQLiteSelectSql` CTE parsing, named `WINDOW` expansion, partition evaluation, and `row_number()` window execution.
