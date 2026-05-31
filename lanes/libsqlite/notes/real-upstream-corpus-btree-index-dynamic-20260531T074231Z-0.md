# Real upstream corpus B-tree/index dynamic 20260531T074231Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereH.test`
- `whereH-1.1` through `whereH-8.2`: composite index planner preference for
  the longer index that covers the full equality prefix plus the range/order
  column, independent of creation order.

Focused coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereHCompositeSupersetIndexCases()`
  with 1,000 dynamic cases over the eight upstream `whereH` sections.
- Added `SQLiteRealUpstreamBtreeWhereHCompositeSupersetDynamicTest.php` with
  1,003 TestRunner PASS cases covering source range, invalid-size validation,
  dependency closure, and all 1,000 behavior rows.

Non-overlap:

- This owns `whereH.test` composite-superset index ranking only. It does not
  repeat accepted `whereJ` range-cost, `whereK` OR factoring, `whereL/M/N`
  constant-propagation/interstage planning, index-interior merge, root
  collapse, overflow freelist, expression-index range-cost, JSON, WAL, VFS,
  PRAGMA, trigger/FK, UPSERT, or source-neutral cleanup clusters.

Dependency closure:

- No new support component is needed; the patch reuses the existing
  lane-local B-tree/index dynamic corpus planner and composite index ranking
  metadata helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereHCompositeSupersetDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereHCompositeSupersetDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereHCompositeSupersetDynamicTest.php`
  - `1 test files, 28257 assertions, 0 failures`
  - 1,003 focused TestRunner PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Not run: guard file is absent in this worktree.
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.
