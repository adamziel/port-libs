# real-upstream-corpus-btree-index-dynamic-20260531T151812Z-0

Base accepted HEAD: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream Source Truth

- Hydrated SQLite upstream file:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/where2.test`
- Ported upstream sections:
  `where2-1.1`, `where2-1.3`, `where2-2.1` through `where2-2.6b`,
  `where2-3.1` through `where2-3.2`, `where2-4.1` through `where2-4.6y`,
  `where2-5.1` through `where2-5.2a`, selected `where2-6.*` affinity and
  OR-to-IN cases, `where2-7.1` through `where2-7.2`, selected
  `where2-8.*` multi-column IN cases, and `where2-11.1` / `where2-11.4`.

## Patch

- Added `SQLiteBTreeIndexDynamicCorpusPlan::where2IndexSelectionAndInCases()`.
- Added `SQLiteRealUpstreamBtreeWhere2IndexSelectionDynamicTest.php`.
- Updated lane status for `+1203` focused PASS cases:
  `2928482 -> 2929685`.

## Behavior Covered

- UNIQUE index `i1w` preference over non-unique `i1xy`.
- Rowid equality preference over named index probes.
- `ORDER BY random()` sorter retention/elision depending on uniqueness.
- Constant `ORDER BY abs(5)` sorter elision.
- Forward/reverse rowid scan order with `LIMIT`.
- Multi-layer `IN` constraints over `i1zyx`, `i1xy`, and `tx_xyz`.
- Duplicate `IN` RHS row de-duplication for ascending and descending output.
- OR-to-IN rewrite behavior and unary-plus no-index guards.
- Affinity-sensitive text/integer comparison behavior from ticket #2249.
- Repeated-column index probes over `i11aba` / `i11cccccccc`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere2IndexSelectionDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere2IndexSelectionDynamicTest.php`
  - `1 test files, 25683 assertions, 0 failures`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice owns `where2.test` index-selection, sort-marker, IN-layer,
OR-to-IN, affinity guard, and repeated-column index behavior. It avoids the
accepted `where7`, `where8`, `where9`, `whereG`, `whereJ`, `whereK`, B-tree
page relocation/root-collapse, overflow freelist/freeblock, JSON, WAL, VFS,
PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup slices.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local
B-tree/index dynamic corpus planner and existing TestRunner helpers; it does
not introduce new WordPress-specific classes, methods, examples, or fixture
APIs.
