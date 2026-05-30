# real-upstream-corpus-btree-index-dynamic-20260530T183558Z-0 blocked

Base accepted HEAD: `365df791b359e0dd925a461a6d36ddf8a8d0f5f1`.

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test`
  section `index7-2.1` through `index7-2.104`, covering WITHOUT ROWID table
  updates and partial-index eligibility.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexA.test`
  sections `2.1` and `3.1`, covering rowid and WITHOUT ROWID partial-index
  affinity matrices for TEXT, NUMERIC, and REAL tables.

Blocker:

- This exact B-tree/index dynamic refill is already present on this accepted
  worktree in `SQLiteBTreeIndexDynamicCorpusPlan` and
  `SQLiteBTreeIndexDynamicCorpusPlanTest`.
- Existing focused coverage already includes `999` real upstream `index7`
  dynamic cases and `1080` real upstream `indexA` dynamic cases, plus the
  earlier `btree01`, `btree02`, `index`, `index6`, `index9`, and `indexedby`
  cases.
- The prior lane note
  `real-upstream-corpus-btree-index-dynamic-20260530T181320Z-0.md` already
  owns the `indexA` expansion and reports `+1080` focused PASS cases.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
  passed: `1 test files, 21964 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  passed: `1 test files, 2885 assertions, 0 failures`.

Decision:

- No ready implementation patch is emitted because adding another test loop
  around these same helper rows would be duplicate PASS-line inflation.
- This does not satisfy the hard handoff floor as new non-overlapping coverage.

Next larger batch to try:

- Move to a different real upstream B-tree/index family, preferably
  `autoindex1.test` plus `autoindex4.test` automatic-index planner/stat cases,
  or `index3.test`/`index5.test`/`indexedby.test` sections not already covered
  by `SQLiteBTreeIndexDynamicCorpusPlan`.
- The next worker should first prove non-overlap against
  `SQLiteBTreeIndexDynamicCorpusPlanTest` and then batch at least `1000`
  distinct TestRunner PASS cases or `5000` behavior assertions from real
  upstream SQLite scenarios.

Dependency closure:

- No new support component is needed for this blocked refill. The existing
  B-tree/index dynamic corpus reuses lane-local B-tree/index page, record,
  planner, partial-index, and cursor-case helpers.
