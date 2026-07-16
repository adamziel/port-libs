# real-upstream-corpus-select-core-dynamic-20260531T022137Z-0

Session: `port-dev-sqlite-yield-dyn-real-select-20260531T022137Z`
Base accepted HEAD: `a17218f2cb8d9470c5635d8abf1711981a8d7bfc`

Implemented one non-overlapping real upstream SELECT core cluster from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- Upstream scenarios: `selectB-3.25` through `selectB-6.25`

Behavior moved:

- `SQLiteSelectSql` now accepts SQLite's postfix predicate spelling
  `expr NOT NULL` as `expr IS NOT NULL`.
- Added `SQLiteRealUpstreamSelectBNotNullArithmeticDynamicTest.php` with one
  upstream citation case plus 1,000 dynamic cases over the `selectB-*.25`
  derived compound SELECT shape:
  `SELECT x+y FROM (...) WHERE y+x NOT NULL ORDER BY 1`.
- The dynamic matrix varies source rows, LEFT JOIN matches/misses, arithmetic
  projected compound arms, NULL filtering, numeric ordering, edge values, and
  result fingerprints.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBNotNullArithmeticDynamicTest.php`
- Result: `1 test files, 7004 assertions, 0 failures`
- PASS-line growth from this focused file: `1001`

Non-overlap:

- This ports the remaining `selectB.test` postfix `NOT NULL` arithmetic filter
  behavior. It does not repeat accepted SELECT text dispatch, JOIN text,
  GROUP BY text, expression ORDER BY, SELECT subquery, selectC alias,
  selectD parenthesized FROM/JOIN, selectE compound collation/error, selectH
  schema DISTINCT/UNION, JSON table source/cursor/constraint, B-tree, pager,
  VFS, WAL, trigger/FK, UPSERT, or metadata-only runner rows.
- Mapped denominator coverage remains unchanged because `selectB.test` is
  already mapped in the complete upstream manifest.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local
  `SQLiteSelectSql` parser/executor, compound SELECT, LEFT JOIN, arithmetic
  expression, NULL predicate, and ORDER BY machinery.
