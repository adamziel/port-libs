# real-upstream-corpus-btree-index-dynamic-20260530T204930Z-0

- Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexfault.test`.
- Ported sections: `indexfault-1.1`, `indexfault-2.1`, `indexfault-2.2`, `indexfault-3.1`, and `indexfault-3.3`.
- Focused growth: 1,000 distinct TestRunner PASS cases covering CREATE INDEX under malloc, `xOpen`, and `xWrite` fault injection, with clean retryable error handling, preserved row counts, and `PRAGMA integrity_check`-equivalent `ok` outcomes.
- Non-overlap: avoids accepted `indexA` join/affinity, `index2` wide index, `index4` large build, `index5` sequential write, `index6` partial index, `index7` stat mutation, B-tree page move/root-collapse/overflow freelist, and metadata-only runner admission surfaces.
- Dependency closure: no new support component is needed; this reuses the existing B-tree/index dynamic corpus plan and TestRunner harness.
