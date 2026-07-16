# real-upstream-corpus-select-core-dynamic-20260531T010622Z-0

Base accepted HEAD: `714d8628d70df34f443545659c4afed0ff8c4b1b`.

Ported real upstream SQLite SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `select1-18.2`: nested correlated `BETWEEN` / `IN` predicates over repeated `t2, t1` sources, including the `x COLLATE rtrim` subquery arm.

Implementation movement:

- Fixed `SQLiteSelectResult` join row merging so repeated qualified column keys from nested/repeated table references are retained under generated `rightN.` internal keys instead of aborting as ambiguous. Unqualified duplicate columns remain guarded.

Focused PHP coverage:

- Added `SQLiteRealUpstreamSelect1CorrelatedBetweenDynamicTest.php`.
- 1,251 distinct TestRunner PASS cases.
- 6,005 behavior assertions.
- Dynamic generic rows vary true and false correlated `BETWEEN`/`IN` outcomes across 1,250 generated cases.

Non-overlap:

- This owns the previously blocked `select1-18.2` nested correlated source subcluster.
- It does not repeat accepted `select1` projection/order/aggregate batches, `selectG` VALUES behavior, `selectH` omit-unused and empty-right compound behavior, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, VFS/WAL/B-tree surfaces, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select1.test` is already present in the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1CorrelatedBetweenDynamicTest.php`
  - Result: `1 test files, 6005 assertions, 0 failures`
  - PASS lines: `1251`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectPredicate`, scalar subquery, derived-table, join, collation, and SELECT result execution.
