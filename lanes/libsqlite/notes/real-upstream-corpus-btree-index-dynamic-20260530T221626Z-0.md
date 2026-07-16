# real-upstream-corpus-btree-index-dynamic-20260530T221626Z-0

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T221626Z-0`

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index9.test`.
- Upstream sections covered: `index9-1.1` through `index9-4.5`.
- Focus: bound-variable partial-index proof behavior around integer boundary predicates, nearby real/string/NULL values, commuted min-int comparison, `ORDER BY x` planner rows, and QPSG-disabled partial-index admission.

Focused coverage:

- Added `SQLiteRealUpstreamBtreeIndex9BoundDynamicTest.php`.
- New focused TestRunner PASS cases: `1002`.
- New focused assertions: `12414`.

Non-overlap:

- Existing accepted B-tree/index dynamic coverage included finite `index9-1.1` through `index9-4.5` rows and separate `index8` ORDER BY/LIMIT, `index7` partial-index, `indexA` affinity/planner, `index4` build, `index5` write-order, and expression-index batches.
- This owns a dynamic expansion of the real upstream `index9.test` bound-value matrix only. It does not repeat B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, index-interior merge, index8 ORDER BY/LIMIT, index7 partial-index routing, or metadata-only upstream runner rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex9BoundDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex9BoundDynamicTest.php`

Dependency closure: no new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and partial-index bound-value proof helpers.
