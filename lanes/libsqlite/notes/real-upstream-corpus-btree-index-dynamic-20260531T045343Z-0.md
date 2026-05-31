# real-upstream-corpus-btree-index-dynamic-20260531T045343Z-0

Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/where9.test`
- Ported sections: `where9-1.2.1`, `where9-1.2.2`, `where9-1.2.5`, `where9-1.3.1`, `where9-4.1` through `where9-4.8`, `where9-5.1` through `where9-5.3`, and `where9-6.2.2` through `where9-6.3.2`.

## Behavior Added

Added a dynamic B-tree/index planner corpus for upstream multi-index OR behavior:

- indexed nullable-column OR unions over `t1b`, `t1c`, and `t1d`;
- unary-plus deoptimization of an OR arm;
- `INDEXED BY` and `NOT INDEXED` interaction with OR-clause planning;
- equality/range plan preference from `where9-5.*`;
- OR-clause `DELETE` and `UPDATE` mutation metadata preserving upstream row sets.

Focused movement: `+1003` TestRunner PASS lines and `15997` assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere9MultiIndexOrDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere9MultiIndexOrDynamicTest.php` passed: `1 test files, 15997 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component needed. This reuses the lane-local B-tree/index dynamic corpus planner and records upstream planner/mutation metadata directly from hydrated SQLite upstream `where9.test`.
