# real-upstream-corpus-select-core-dynamic-20260531T073325Z-0

- Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`.
- Ported sections: `selectC-1.1`, `selectC-1.2`, `selectC-1.5`, `selectC-1.8`, and `selectC-1.13`.
- Focused coverage: 1,001 TestRunner PASS cases, including 1,000 dynamic behavior cases over SELECT-list aliases in `WHERE`/`HAVING`, `DISTINCT` de-duplication, concatenated expressions, and `upper()` grouping/order behavior.
- Non-overlap: avoids accepted SELECT JOIN text, GROUP BY text, subqueries, expression ORDER BY, comma LIMIT, selectB join arithmetic, selectG VALUES, select9 compound LIMIT, JSON table SELECT sources, WAL/VFS/B-tree clusters, and source-neutral cleanup.
- Dependency closure: no new support component needed; this reuses the existing `SQLiteSelectSql` parser/executor and hydrated upstream SQLite test corpus.
