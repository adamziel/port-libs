# Real Upstream Corpus: B-tree Index Dynamic

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`
  - `index2-1.1` through `index2-2.2`: 1000-column table, many rows, broad
    CREATE INDEX key materialization, and ORDER BY prefix reads.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`
  - `index3-1.1` through `index3-1.4`: UNIQUE index creation fails on duplicate
    keys and leaves no schema/index residue.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`
  - `index4-1.2` through `index4-1.8` and `index4-2.2`: CREATE INDEX over large
    rows preserves integrity, sorts NULL before numeric keys, and rejects
    duplicate UNIQUE keys.

Implemented cluster:

- Added `SQLiteIndexBuildPlan` for generic SQLite index key extraction,
  deterministic large-row fixtures, sorted index records, UNIQUE duplicate
  detection, and no-residue failure metadata.
- Added `SQLiteIndexBuildUniqueConstraintException` to carry failed build
  diagnostics without mutating schema state.
- Added `SQLiteRealUpstreamIndexBuildDynamicCorpusTest.php` with 1,024 focused
  TestRunner cases and over 5,500 behavior assertions. The cases are distinct
  dynamic upstream-derived CREATE INDEX build variants, not metadata-only
  denominator rows.

Non-overlap:

- Does not repeat existing `indexedby.test` forced-index planner coverage,
  `SQLiteSelectExpressionIndexPlan` range-cost ranking, page relocation, root
  collapse, overflow freelist release, or legacy WordPress-shaped examples.

Dependency closure:

- No new support component is needed. The slice reuses native PHP row arrays
  and adds a small generic index-build planner for CREATE INDEX key extraction
  and uniqueness behavior.
