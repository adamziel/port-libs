# real-upstream-corpus-select-core-dynamic-20260531T031258Z-0

Status: focused real-upstream SELECT core dynamic corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`

Owned upstream scenarios:

- `select4-16.1`: aggregate subquery joined to the base table after the upstream coroutine/subquery enhancement.
- `select4-16.2`: same aggregate subquery path through `CROSS JOIN`.
- `select4-16.3`: same aggregate subquery path through `LEFT JOIN` with a filtering predicate that preserves the matched rows.

Implemented coverage:

- Added `SQLiteRealUpstreamSelect4AggregateJoinDynamicTest.php`.
- Ports the select4 aggregate-subquery join shape to generic `stream_items` rows.
- Adds 1 source-citation test, 1 canonical upstream-shape test, and 1200 dynamic cases.
- Dynamic cases vary the aggregate `a >= threshold` predicate, ordinary/CROSS/LEFT join operators, and row payload offsets while preserving the upstream `SELECT a,max(b) ... GROUP BY a` derived table joined back to the base table.

Expected movement:

- Focused PASS-line growth: `+1202`.
- Behavior assertions: focused run `1 test files / 6011 assertions / 0 failures`.
- Mapped denominator growth: none; `select4.test` is already in the mapped upstream inventory.

Non-overlap:

- This slice owns `select4.test` `select4-16.1` through `select4-16.3`.
- It does not repeat the already-present `select4-14` VALUES compound cluster, `select4-15.1` coroutine/Yield compound `UNION` cluster, accepted SELECT expression `ORDER BY`, grouped SELECT text, JOIN text, JSON table SELECT source/cursor behavior, or metadata-only runner rows.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `SQLiteSelectSql` parser/executor and aggregate/derived-table join machinery.
