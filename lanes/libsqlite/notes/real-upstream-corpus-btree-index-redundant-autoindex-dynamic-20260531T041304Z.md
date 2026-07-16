# Real Upstream Corpus B-tree Index Redundant Autoindex Dynamic

Slice: `real-upstream-corpus-btree-index-dynamic-20260531T041304Z-0`

Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
- Sections `index-16.1` through `index-17.4`

Behavior added:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexRedundantConstraintAutoindexCases()`.
- Added 1000 dynamic focused cases for redundant `UNIQUE`/`PRIMARY KEY` constraint coalescing into stable `sqlite_autoindex_t7_N` names.
- Preserved protected autoindex drop behavior for `DROP INDEX` and `DROP INDEX IF EXISTS`, including the missing-name `IF EXISTS` success case.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexRedundantAutoindexDynamicTest.php`
- Result: `1 test files, 12895 assertions, 0 failures`
- PASS lines: `1003`

Non-overlap:

- This slice does not repeat the accepted B-tree index sort-order, index19 conflict-policy, index catalog lifecycle, primary-autoindex static, index-interior merge, page relocation, overflow freelist release, or expression-index range-cost clusters.
- This slice owns the upstream redundant-constraint autoindex coalescing and protected-autoindex drop matrix from `index.test` sections `index-16.1` through `index-17.4`.

Dependency closure:

- No new support component is needed; this reuses the lane-local B-tree/index dynamic corpus planner and generic SQLite catalog/autoindex semantics.
