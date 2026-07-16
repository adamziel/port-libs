# real-upstream-corpus-btree-index-dynamic-20260530T184537Z-0 blocked

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`.

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  sections `index-1` through `index-6`, covering CREATE INDEX catalog rows,
  missing table/column errors, many indexes on one table, lookup behavior
  through created/dropped indexes, sqlite_schema index rejection, duplicate
  index names, and table-drop index cleanup.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`
  sections `index2-1.1` through `index2-2.2`, covering 1000-column tables,
  1000-column index creation, and wide index ORDER BY/LIMIT behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree01.test`
  sections `btree01-1.1` through `btree01-2.2`, covering large payload
  balance stress and WITHOUT ROWID overflow cursor join behavior.

Blocker:

- The current accepted worktree already contains this B-tree/index dynamic
  family in `SQLiteBTreeIndexDynamicCorpusPlan`,
  `SQLiteBTreeIndexDynamicCorpusPlanTest.php`, and
  `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Existing coverage already cites and exercises the non-overlapping high-yield
  portions of `btree01.test`, `btree02.test`, `index.test`, `index2.test`,
  `index4.test`, `index6.test`, `index7.test`, `index8.test`, `index9.test`,
  `indexA.test`, and `indexedby.test`.
- The immediately prior blocked note
  `real-upstream-corpus-btree-index-dynamic-20260530T183558Z-0-blocked.md`
  reached the same conclusion for the largest remaining obvious refill
  candidates: `index7.test` and `indexA.test` are already modeled, and another
  loop around those rows would be duplicate PASS-line inflation rather than new
  upstream behavior.
- A small hand-port of the early `index.test`, `index2.test`, or `btree01.test`
  rows would not satisfy the active hard handoff floor as new non-overlapping
  coverage, because the core behaviors are already represented by the accepted
  dynamic corpus and by older schema/index/pragma integrity slices.

Decision:

- No ready implementation patch is emitted for this micro-slice.
- No `phpPass`, mapped denominator, or focused PASS-line growth is claimed.
- This note is the lane-local blocker artifact required by the hard handoff
  floor when a fresh real-upstream corpus worker cannot honestly add at least
  `1000` distinct TestRunner PASS cases, `5000` new behavior assertions, a
  blocker fix that unlocks `2000` PASS cases, or guarded mapped denominator
  coverage.

Next larger batch to try:

- Pivot out of already-covered B-tree/index dynamic helpers and target a
  different real upstream family with enough unmodeled rows for one clean batch:
  `autoindex1.test` plus `autoindex4.test` automatic-index planner behavior, or
  the remaining `bestindex*.test` virtual-table planner corpus if the lane can
  first add a bounded generic best-index planner model.
- Before editing, prove non-overlap against
  `SQLiteBTreeIndexDynamicCorpusPlanTest.php` and
  `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`, then batch at least
  `1000` distinct focused TestRunner cases or `5000` behavior assertions from
  real upstream scenario names.

Dependency closure:

- No new support component is needed for this blocked B-tree/index refill. The
  missing prerequisite is a different non-overlapping upstream target or a
  reusable lane-local corpus adapter for automatic-index / best-index planner
  sections large enough to satisfy the hard floor.
