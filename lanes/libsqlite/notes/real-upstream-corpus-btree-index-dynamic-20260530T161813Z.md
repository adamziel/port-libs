# Real Upstream Corpus: B-tree / Index Dynamic

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree01.test`
  (`btree01-1.2.1` through `btree01-1.8.31`) and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  (`index-4.2` through `index-4.12`).
- Ported behavior: btree01 dynamic balance stress records page size, target row,
  shrink/expand BLOB sizes, table-leaf local payload, overflow payload, overflow
  page count, and integrity result for all upstream loop variants; index.test
  dynamic create/drop lookup cases preserve active index names and lookup
  results while indexes are dropped and recreated.
- Focused assertion count: 211 btree01 cases * 10 assertions plus 11 index
  cases * 6 assertions = 2176 focused assertions across 222 real upstream
  behavior cases.
- Non-overlap: avoids accepted date/VFS/window real-corpus coverage and avoids
  prior B-tree page-move, root-collapse, overflow-freelist-release, and static
  metadata-only suite admission rows. This is behavior-backed PHP coverage
  using real upstream script ids and generic application names only.
- Dependency closure: no new support component is needed; the batch reuses
  existing native PHP record, B-tree table leaf, and corpus-plan primitives.
