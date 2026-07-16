# real-upstream-corpus-btree-index-dynamic-20260530T185819Z-0 blocked

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`.

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree01.test`
  sections `btree01-1.1` through `btree01-2.2`, covering large payload
  balance stress and WITHOUT ROWID overflow cursor join behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  sections `index-1` through `index-14`, covering index lifecycle,
  duplicate-key delete/reinsert behavior, and mixed-type index ordering.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`
  section `index5-1.1` through `index5-1.3`, covering sequential page-write
  behavior during large CREATE INDEX operations.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test`
  section `index7-2.1` through `index7-2.104`, covering WITHOUT ROWID updates
  and partial-index eligibility.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexA.test`
  sections `2.1` and `3.1`, covering rowid and WITHOUT ROWID partial-index
  affinity matrices.

Blocker:

- This accepted worktree already contains the B-tree/index dynamic corpus in
  `SQLiteBTreeIndexDynamicCorpusPlanTest.php`,
  `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`,
  `SQLiteRealUpstreamBtreeIndexDynamicCorpusTest.php`,
  `SQLiteRealUpstreamBtreeIndexDynamicWideSchemaTest.php`, and related helper
  coverage.
- `SQLiteBTreeIndexDynamicCorpusPlanTest.php` already models `btree01.test`,
  `btree02.test`, `index.test`, `index5.test`, `index6.test`, `index7.test`,
  `index9.test`, `indexA.test`, and `indexedby.test`, including `999`
  `index7` dynamic cases, `1080` `indexA` dynamic cases, and `1200`
  `index5` sequential-write cases.
- The newer focused corpus files also cover accepted `index.test` duplicate
  delete/reinsert and mixed-type ordering rows, plus `index2.test` wide-schema
  rows. Adding another loop over those rows would inflate PASS lines without
  adding distinct upstream behavior.
- Prior blocked notes
  `real-upstream-corpus-btree-index-dynamic-20260530T183558Z-0-blocked.md`
  and
  `real-upstream-corpus-btree-index-dynamic-20260530T184537Z-0-blocked.md`
  reached the same conclusion for the largest obvious refill candidates.

Decision:

- No ready implementation patch is emitted for this micro-slice.
- No `phpPass`, mapped denominator, or focused PASS-line growth is claimed.
- This note is the lane-local blocker artifact required by the hard handoff
  floor when the current real-upstream corpus worker cannot honestly add at
  least `1000` distinct TestRunner PASS cases, `5000` new behavior assertions,
  a blocker fix that unlocks `2000` PASS cases, or guarded mapped denominator
  coverage.

Next larger batch to try:

- Pivot away from the already-covered B-tree/index dynamic corpus and target a
  different upstream family with enough unmodeled rows for one clean batch:
  `autoindex1.test` plus `autoindex4.test` automatic-index planner/stat
  behavior, or `bestindex*.test` virtual-table planner behavior after adding a
  bounded generic best-index planner model.
- Before editing, prove non-overlap against the existing B-tree/index dynamic
  files and batch at least `1000` distinct focused TestRunner cases or `5000`
  behavior assertions from real upstream scenario names.

Dependency closure:

- No new support component is needed for this blocked refill. The missing
  prerequisite is a different non-overlapping upstream target or a reusable
  lane-local corpus adapter for automatic-index / best-index planner sections
  large enough to satisfy the hard floor.
