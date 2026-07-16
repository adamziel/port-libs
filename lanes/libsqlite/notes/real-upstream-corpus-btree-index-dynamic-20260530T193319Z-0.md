# real-upstream-corpus-btree-index-dynamic-20260530T193319Z-0

Added `SQLiteRealUpstreamIndexAffinityAutoindexDynamicCorpusTest.php` as a real upstream B-tree/index corpus follow-up.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  - `index-15.2` and `index-15.3`: NUMERIC affinity converts valid numeric-looking text before index ordering, while malformed exponent/sign strings remain text.
  - `index-16.1` through `index-16.5`: duplicate UNIQUE and PRIMARY KEY constraints over the same column signature create only one autoindex.
  - `index-17.1`: autoindex names stay sequential and internal.

Implemented cluster:

- Extended `SQLiteIndexBuildPlan::build()` with optional per-column affinity normalization so B-tree index keys can model NUMERIC affinity ordering from upstream `index-15`.
- Updated `SQLiteSchemaImportExecutor` autoindex counting to coalesce duplicate UNIQUE/PRIMARY KEY column signatures while preserving distinct constraints.
- Added 1,043 focused TestRunner cases and 4,384 behavior assertions from real upstream index sections.

Non-overlap:

- This does not repeat `indexedby.test` forced-index planner coverage, the prior large `index2`/`index3`/`index4` index-build materialization batch, expression-index range costs, page relocation/root collapse, overflow freelist release, PRAGMA index metadata, or source-neutral cleanup.

Dependency closure:

- No new support component is needed. The slice reuses lane-local schema records and the generic index-build planner, adding bounded native affinity and autoindex-signature behavior.
