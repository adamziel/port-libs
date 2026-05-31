# real-upstream-corpus-select-core-dynamic-20260531T023110Z-0

Session: `port-dev-sqlite-yield-dyn-real-select-20260531T023110Z`
Base accepted HEAD: `0374bb37770e0bf365d4f603a02af1f3e153889e`

Implemented one non-overlapping real upstream SELECT core cluster from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- Upstream scenarios: `selectB-3.17` through `selectB-6.24`

Behavior moved:

- `SQLiteSelectSql` now treats `SELECT DISTINCT(expr)` as SQLite syntax for a
  DISTINCT result set, not as a scalar function named `DISTINCT`.
- Added `SQLiteRealUpstreamSelectBNestedCompoundTailDynamicTest.php` with one
  upstream citation case plus 7,000 dynamic cases over:
  - nested compound `LIMIT`/`OFFSET` tails (`selectB-*.17` and `selectB-*.18`);
  - per-arm `SELECT DISTINCT(expr)` and outer DISTINCT (`selectB-*.19` and
    `selectB-*.20`);
  - expression `ORDER BY` over compound `SELECT *` rows (`selectB-*.21`);
  - constant-arm `UNION ALL` ordering (`selectB-*.22`);
  - arithmetic JOIN and LEFT JOIN compound rows (`selectB-*.23` and
    `selectB-*.24`).

Focused evidence:

- Red-first failure before the source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBNestedCompoundTailDynamicTest.php`
  failed `selectB-19`/`selectB-20` cases with
  `Unsupported SQLite core scalar function: DISTINCT`.
- After the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBNestedCompoundTailDynamicTest.php`
  => `1 test files, 42007 assertions, 0 failures`
- PASS-line growth from this focused file: `7001`.

Non-overlap:

- This owns the `selectB.test` nested compound tail and `DISTINCT(expr)` arm
  cluster after the accepted `selectB-*.25` postfix `NOT NULL` arithmetic
  batch. It does not repeat accepted SELECT text dispatch, JOIN text, GROUP BY
  text, expression ORDER BY, SELECT subquery, selectC alias, selectD
  parenthesized FROM/JOIN, selectE compound collation/error, selectH schema
  DISTINCT/UNION, JSON table source/cursor/constraint, B-tree, pager, VFS, WAL,
  trigger/FK, UPSERT, or metadata-only runner rows.
- Mapped denominator coverage remains unchanged because `selectB.test` is
  already mapped in the complete upstream manifest.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local
  `SQLiteSelectSql` parser/executor, compound SELECT, JOIN/LEFT JOIN, DISTINCT,
  arithmetic expression, ORDER BY, LIMIT/OFFSET, and derived-table machinery.
