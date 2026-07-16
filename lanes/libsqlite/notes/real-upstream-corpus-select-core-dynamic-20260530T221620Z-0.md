# real-upstream-corpus-select-core-dynamic-20260530T221620Z-0

Added `SQLiteRealUpstreamSelectAIntersectExceptDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`

Ported upstream scenarios:

- `selectA-2.41`: `EXCEPT` removes high `b >= 'd'` rows and orders the remaining compound rows by `a,b,c`.
- `selectA-2.45` through `selectA-2.58`: `INTERSECT`/`EXCEPT` low/high row-set behavior with `ORDER BY a DESC`, `ORDER BY b`, `ORDER BY b COLLATE NOCASE`, and multi-term ordering.
- `selectA-2.60` through `selectA-2.63`: high/low `INTERSECT`/`EXCEPT` behavior with `ORDER BY c`, explicit `COLLATE BINARY`, and explicit `COLLATE NOCASE`.
- Each supported upstream scenario is expanded across deterministic `LIMIT 0..10` and `OFFSET 0..10` windows, plus the full result case.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAIntersectExceptDynamicTest.php`
- Result: `1 test files, 18525 assertions, 0 failures`
- Distinct TestRunner PASS cases: `2318`
- Expected `phpPass` movement: `912041 -> 914359`

Non-overlap:

- This owns the later `selectA.test` `INTERSECT`/`EXCEPT` ordering cluster.
- It does not repeat accepted `selectA` early `UNION ALL` compound merge ordering, accepted `selectB` derived compound flattening, accepted `select1` through `select9`, `selectC` through `selectH`, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS/B-tree storage clusters, or metadata-only runner rows.
- `selectA-2.42`, `selectA-2.43`, `selectA-2.44`, `selectA-2.59`, and `selectA-2.64` remain excluded because this row-array fixture path still lacks the table-declared/blob collation propagation needed for those exact upstream orderings. They are not counted as passing coverage in this handoff.

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, `SQLiteSelectCompound`, and `SQLiteBlobValue` support.
- Mapped denominator remains unchanged because `selectA.test` is already present in the hydrated upstream inventory and runner-map evidence.
