# real-upstream-corpus-btree-index-dynamic-20260531T060947Z-0

Status: blocked by current-base overlap.

Accepted base: `cd24ba2f7b741bb89ced6cb6c27264084794565b`.

Attempted upstream source surface:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  sections `index-20.1` through `index-23.1`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree02.test`
  sections `btree02-100` and `btree02-110`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereJ.test`
  sections `whereJ-4.2` and `whereJ-5.1` through `whereJ-5.3`.

Why this slice cannot produce a valid ready handoff:

- `SQLiteRealUpstreamBtreeIndexLateDynamicTest.php`,
  `SQLiteRealUpstreamBtreeIndexTailSchemaAffinityDynamicTest.php`, and
  `SQLiteRealUpstreamBtreeIndexLateLifecycleDynamicTest.php` already cover the
  late `index.test` quoted drop, temp-index scope, expression-index, and REINDEX
  behavior on this base.
- `SQLiteRealUpstreamBtree02SkipNextDynamicTest.php` and
  `SQLiteRealUpstreamBtree02SkipNextRestoreDynamicTest.php` already cover the
  `btree02.test` cursor-mutation/skip-next behavior in dynamic batches.
- `SQLiteRealUpstreamBtreeWhereJRangeCostDynamicTest.php` already covers the
  `whereJ.test` STAT4/stat1 range-cost and multi-index OR planner behavior in
  a 1000-case dynamic corpus.

Adding another test file over these same helper methods would inflate PASS
lines without adding distinct upstream behavior, which violates the real
upstream corpus and hard handoff floor rules. No production blocker was exposed
while inspecting these surfaces.

Recommended next larger batch:

- Move outside the already admitted B-tree/index dynamic family and target a
  known-red broad diagnostic cluster instead, especially default-memory
  pager/WAL pressure, JSON101/JSON1/JSONB regressions, PRAGMA schema expected
  shape, expression IS/unary-plus semantics, or a current app-WAL/rowvalue
  conflict.
- If the supervisor still wants B-tree/index work, assign a specific upstream
  `.test` section not already represented by `SQLiteBTreeIndexDynamicCorpusPlan`
  or one of the `SQLiteRealUpstreamBtree*DynamicTest.php` files, and require
  accepted-base overlap evidence before adding cases.

Dependency closure: no new support component is needed; the blocker is overlap
with existing lane-local B-tree/index corpus helpers and focused tests.
