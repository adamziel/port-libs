# real-upstream-corpus-select-core-dynamic-20260531T052452Z-0

Micro-slice: `real-upstream-corpus-select-core-dynamic-20260531T052452Z-0`

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`

Changed behavior:

- Fixed parser/executor SELECT JOIN behavior for SQLite `USING` and `NATURAL`
  joins by carrying the join column list into the query plan and materializing
  SQLite's coalesced bare result column name for those join types only.
- Added `SQLiteRealUpstreamSelectCoreDynamicJoinWhere20260531Test.php` with
  1,282 focused TestRunner PASS cases and 6,410 assertions.
- The red-first focused run failed with `SQLite SELECT expression column k is
  ambiguous` for upstream `SELECT k ... LEFT JOIN ... USING(k)` and
  `NATURAL JOIN` cases. After the fix, all focused cases pass.

Real upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- `e_select-3.2.1a`: `LEFT JOIN USING(k)` preserves all left rows before WHERE.
- `e_select-3.2.1b`: `WHERE x2.k` filters nullable right-side rows after the
  join.
- `e_select-3.2.2`: `WHERE x2.k IS NULL` keeps unmatched `LEFT JOIN` rows.
- `e_select-3.2.3` / `e_select-3.2.4`: `NATURAL JOIN` matches shared columns
  before WHERE truthiness is evaluated.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicJoinWhere20260531Test.php`
  - Before fix: `1 test files, 1290 assertions, 1024 failures`
  - After fix: `1 test files, 6410 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicWhereTruthiness20260531Test.php`
  - Result: `1 test files, 4327 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - Result: no syntax errors detected
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
  - Result: no syntax errors detected
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicJoinWhere20260531Test.php`
  - Result: no syntax errors detected
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicJoinWhere20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicWhereTruthiness20260531Test.php`
  - Result: `2 test files, 10737 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Result: clean
- `SQLiteNoWordPressSpecificApiTest.php`
  - Result: not run; guard file is not present in this isolated worktree.

Expected selected throughput movement:

- +1,282 focused TestRunner PASS lines if admitted as a selected libsqlite
  corpus file.
- Mapped denominator remains unchanged because `e_select.test` is already part
  of the hydrated upstream manifest coverage.

Non-overlap:

- This owns `e_select.test` section 3.2 post-join WHERE filtering and coalesced
  `USING`/`NATURAL` column resolution.
- It does not repeat existing `e_select-3.1` WHERE truthiness, grouped SELECT
  text, expression ORDER BY, SELECT subquery/JOIN text batches, JSON table
  source/cursor/constraint work, WAL/VFS/B-tree behavior, or metadata-only
  runner rows.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded
  `SQLiteSelectSql` and `SQLiteSelectQuery` executor path.
