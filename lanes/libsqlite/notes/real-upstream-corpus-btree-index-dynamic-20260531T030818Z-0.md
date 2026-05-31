# real-upstream-corpus-btree-index-dynamic-20260531T030818Z-0

Base accepted HEAD: `fae55e1960d0133f25e28bd517f3a8c8e56c4545`.

Added a focused real upstream B-tree/index dynamic test file for SQLite upstream:

- `test/index7.test` sections `index7-3.1` through `index7-8.1`: WITHOUT ROWID partial UNIQUE indexes, excluded sentinel duplicate admission, database-qualified partial predicates, view route use of a partial index, syntax-error guard, boolean predicate stability, and sparse-stat planner use.
- `test/indexA.test` sections `indexA-1.1` through `indexA-8.1`: partial-index implication across TEXT, NUMERIC, REAL, and INTEGER affinity, rowid and WITHOUT ROWID storage, collation error handling, aggregate covering-index routing, bloom-filter/covering join planning, INDEXED BY forcing, and commuted constant predicates.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex7IndexATest.php`
- Result: `1 test files, 34487 assertions, 0 failures`.
- Distinct TestRunner PASS cases: `2203`.

Non-overlap:

- This slice does not touch accepted `index2`, `index4`, or `index5` dynamic corpus files, B-tree page relocation, overflow freelist release, root collapse, index-interior merge, JSON table/source behavior, WAL/VFS application, or SELECT SQL expression ORDER BY work.
- It reuses existing lane-local B-tree/index dynamic corpus planners and adds focused tests only.

Dependency closure:

- No new support component needed. The slice reuses existing native PHP dynamic corpus planners for partial-index predicates, WITHOUT ROWID planner evidence, affinity implication, and result-row fixtures.

Root harness:

- Not run; isolated micro-slice.
