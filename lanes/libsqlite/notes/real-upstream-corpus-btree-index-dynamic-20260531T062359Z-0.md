# real-upstream-corpus-btree-index-dynamic-20260531T062359Z-0

Base accepted HEAD: `68a3731675769814ce7d56857d9182ac7f8b3613`.

Implemented a real upstream B-tree/index corpus slice from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/btree02.test`:

- `btree02-100`: WITHOUT ROWID table setup with composite primary key and
  secondary index.
- `btree02-110`: alternating insert/delete commits during an active scan,
  preserving cursor position across skip-next restore and ending at 10 rows.

New focused coverage:

- `SQLiteBTreeIndexDynamicCorpusPlan::btree02CursorSkipNextMutationCases(1000)`
- `SQLiteRealUpstreamBtree02CursorMutationDynamicTest.php`
- 1000 distinct dynamic PASS cases, plus corpus count, invalid-size guard, and
  dependency-closure assertions.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtree02CursorMutationDynamicTest.php`
- Result: `1 test files, 26007 assertions, 0 failures`, `1003` PASS lines.

Non-overlap:

- Does not repeat accepted B-tree table/index page relocation, index-interior
  merge, root collapse, overflow freelist release, bulk overflow freeblocks,
  index2 wide-column, indexfault temp readback, where8/where9 OR, or
  expression-index/range-cost coverage.
- This slice is specifically the upstream `btree02.test` cursor restore /
  `CURSOR_SKIPNEXT` mutation behavior.

Dependency closure:

- No new support component needed; reuses the lane-local B-tree/index dynamic
  corpus planner for WITHOUT ROWID cursor mutation and secondary-index scan
  restoration.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtree02CursorMutationDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtree02CursorMutationDynamicTest.php` passed with `26007` assertions and `0` failures.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.
