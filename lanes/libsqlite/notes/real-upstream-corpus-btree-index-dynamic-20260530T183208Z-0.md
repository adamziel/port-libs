# Real Upstream Corpus: B-tree / Index Dynamic indexA Matrix

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T183208Z-0`
- Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexA.test`
- Upstream scenarios covered: `indexA-2.1` and `indexA-3.1`, expanded as the upstream rowid and WITHOUT ROWID partial-index affinity matrix over `TEXT`, `NUMERIC`, and `REAL` table affinity, five partial-index predicate setups, and four lookup predicate forms.

Behavior delta:

- Wired the existing `SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialAffinityMatrixCases()` generator into `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Adds 360 distinct focused TestRunner PASS cases and approximately 5,760 new behavior assertions over real upstream indexA partial-index affinity combinations.
- The focused file now verifies `676` PASS lines and `9,293` assertions with zero failures.

Non-overlap:

- This does not add metadata-only runner rows, generated fake upstream script names, mapped-denominator claims, source-neutral cleanup, WordPress-specific APIs, B-tree page-move/freeblock/freelist duplication, WAL/VFS behavior, JSON planner behavior, or suite evidence rows.
- The new surface is specifically upstream `indexA.test` rowid and WITHOUT ROWID partial-index affinity behavior, not the already accepted `index6`, `index9`, `indexedby`, B-tree page relocation, root-collapse, overflow freelist, or expression-index range-cost clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing native B-tree/index corpus planning, SQLite affinity comparison modeling, and focused PHP TestRunner infrastructure.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed: `1 test files, 9293 assertions, 0 failures`, `676` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` was not run because that guard file is absent in this worktree.
