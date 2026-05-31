# real-upstream-corpus-pragma-schema-dynamic-20260531T043023Z-0

- Base accepted HEAD: `9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`, section `pragma4-1.*`.
- Ported behavior: dynamic PRAGMA result-shape semantics. Query-form PRAGMAs return one column/row, assignment forms return zero columns/rows, and `shrink_memory` / `case_sensitive_like` return zero columns in both query and assignment forms.
- Focused PHP coverage: `SQLiteRealUpstreamCorpusPragmaSchemaDynamicResultShape20260531Test.php` adds `1001` focused TestRunner cases and `4406` assertions.
- Non-overlap: avoids already accepted schema-version reload, prepared expiry, table-valued PRAGMA joins, virtual PRAGMA rowsets, quoted schema names, page-count, database-list, table-list, hidden constraint, and index_xinfo dynamic slices.
- Dependency closure: no new support component needed; this reuses the lane-local PRAGMA parser/result modeling surface and does not require external SQLite runners.
