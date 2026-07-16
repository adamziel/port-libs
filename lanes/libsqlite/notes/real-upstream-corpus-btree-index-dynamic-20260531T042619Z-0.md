# Real Upstream Corpus B-tree/Index Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-btree-20260531T042619Z`
Base accepted HEAD: `9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`
- Sections: `index5-1.1`, `index5-1.2`, `index5-1.3`
- Behavior: bulk `CREATE INDEX i1 ON t1(x)` over 100000 rows records `testvfs` `xWrite` database page offsets and requires forward page writes to dominate backward plus noncontiguous writes by more than 2x.

## Ported Coverage

- Added `SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialIndexBuildWriteCases()`.
- Extended `SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php` while preserving the existing accepted summary/transition cases.
- Focused growth: `1203` new TestRunner PASS lines from the appended dynamic guard block.
- Focused file verification: `2407` total TestRunner PASS lines, `33618` behavior assertions.
- Non-overlap: this targets `index5.test` sequential bulk-index write ordering and does not repeat accepted `index8`, `index9`, `index6`, index tail schema-affinity, B-tree page move, overflow freelist, or freeblock materialization slices.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php`: `1 test files, 33618 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local B-tree/index dynamic corpus planner and models the upstream `xWrite` page-order guard semantics directly.
