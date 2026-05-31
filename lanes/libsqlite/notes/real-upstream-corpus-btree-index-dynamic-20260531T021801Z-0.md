# Real Upstream Corpus B-tree/Index Dynamic 2026-05-31

Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`

Owned upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexA.test`
- Sections `bestindexA-1.1` through `bestindexA-1.9`

Implemented behavior:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::bestindexAVirtualTableConstraintCases()`.
- Added `SQLiteRealUpstreamBtreeBestIndexADynamicTest.php` with 1000 dynamic TestRunner cases plus source-range, rejection, and dependency-closure checks.
- Ported virtual-table xBestIndex constraint reporting for equality, LIMIT, non-column expression omission, overloaded two-argument function constraints, inequality, and commuted equality.

Non-overlap:

- Existing accepted/dynamic B-tree index coverage already includes `bestindex3`, `bestindex5`, `index2`, `index4`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `autoindex1`, `autoindex4`, and `autoindex5` families.
- This slice adds the previously absent `bestindexA.test` xBestIndex constraint-reporting family and does not touch accepted B-tree page relocation, overflow freelist release, index-interior merge, JSON table, WAL, VFS, or SQL text executor clusters.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndexADynamicTest.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndexADynamicTest.php`
  - `1 test files, 22005 assertions, 0 failures`
  - 1003 focused PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndex5DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  - `2 test files, 345406 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.
- `SQLiteNoWordPressSpecificApiTest.php`
  - Not present in this worktree.

Dependency closure:

- No new support component needed. The slice reuses the lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex constraint accounting.
