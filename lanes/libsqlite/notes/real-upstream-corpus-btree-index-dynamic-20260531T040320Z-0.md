# real-upstream-corpus-btree-index-dynamic-20260531T040320Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index8.test`.
- Ported sections: `index8` `1.0`, `1.0eqp`, `1.1`, and `1.1eqp`.
- Behavior cluster: `ORDER BY a,b LIMIT 2` planner choice over `t1(a,b,c,d)` with `WHERE c=4`, proving a scan using `t1abc(a,b,c)` when the WHERE column is covered and fallback to a table scan plus temp sorter after replacing it with `t1abd(a,b,d)`.
- Focused PHP growth: `SQLiteRealUpstreamBtreeIndex8OrderByLimitDynamicTest.php` adds 1203 TestRunner PASS cases backed by the real upstream sections.
- Non-overlap: avoids accepted `indexA`, `index3`, `index4`, `index5`, `index6`, `index7`, `whereL/M/N`, page-move, overflow/freelist, root-collapse, and expression ORDER BY clusters; this slice owns `index8.test` ORDER BY/LIMIT index-scan selection only.
- Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner, composite-index coverage, ORDER BY/LIMIT planner detail, and result-row helpers.
