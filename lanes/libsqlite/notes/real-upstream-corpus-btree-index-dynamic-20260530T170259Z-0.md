# Real Upstream Corpus B-tree/Index Dynamic Slice

- Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`
- Ported scenario ranges:
  - `index.test` `index-1.1..1.2`: CREATE INDEX catalog rows and DROP TABLE index cleanup.
  - `index.test` `index-2.1..2.2`: missing table/column diagnostics.
  - `index.test` `index-3.1..3.3`: many indexes on one table and drop cleanup.
  - `index.test` `index-6.1..6.2b`: duplicate names, `IF NOT EXISTS`, and table-name collisions.
  - `index.test` `index-10.0..10.8`: duplicate-key indexed lookup and delete behavior.
  - `index.test` `index-12.1..12.7`, `index-15.2..15.3`: NUMERIC affinity and scientific numeric strings through indexed comparison order.
  - `index.test` `index-14.1..14.11`: composite index sort/search ordering across NULL, numeric, and text values.
  - `index.test` `index-16.1..17.4`, `index-19.6`: automatic constraint indexes and conflict-clause diagnostics.
  - `index2.test` `index2-1.1..2.2`: 1000-column table/index and wide ORDER BY prefix behavior.
- Focused assertion/PASS count: `263` assertions and `263` PASS lines in `SQLiteRealUpstreamCorpusIndexLifecycleTest.php`.
- Non-overlap: avoids accepted `index6.test` partial-index admission, `indexedby.test` access-path planning, expression-index range-cost slices, B-tree page relocation/root-collapse/overflow freelist release, and JSON/VFS/WAL accepted clusters. This slice focuses on index lifecycle, automatic-index semantics, duplicate-key scans, affinity comparison, and wide-index ordering from `index.test`/`index2.test`.
- Dependency closure: no new support component required; the patch adds a bounded native PHP index lifecycle/indexed-row behavior helper under `lanes/libsqlite/src`.
- Root harness: not run - isolated micro-slice.
