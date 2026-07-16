# real-upstream-corpus-select-core-dynamic-20260531T162339Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`.

Upstream sections covered:

- `e_select-4.15`: grouped result-set expressions are evaluated once per group; aggregate result expressions read all rows in the group; non-aggregate result expressions use one consistent sample row from the group.
- `e_select.4.16 -count`: each input group contributes exactly one output row.

Patch evidence:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupResultDynamic20260531T162339ZTest.php`.
- The focused test hydrates the upstream `c1`, `c2`, and `c3` shape, then runs 750 dynamic SELECT SQL cases covering `sum()`, `max()`, aggregate arithmetic, joined `count()`/`round(avg())`, non-aggregate grouped rows, HAVING filtering, `NATURAL JOIN`, and grouped row-count cases.
- Focused command: `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupResultDynamic20260531T162339ZTest.php`.
- Result: `1 test files, 42765 assertions, 0 failures`.
- Selected evidence delta: `3350074 -> 3392839` pass / `0` fail (`+42765` assertions). Mapped coverage remains `1589 / 1589`; this is assertion growth against already mapped upstream inventory.

Non-overlap:

- This slice owns `e_select-4.15` grouped result-expression semantics and `e_select.4.16` row-count behavior.
- It avoids accepted `e_select-4.13` HAVING min/max, `e_select-4.11` grouped collation, DISTINCT/ALL, compound core/order, LIMIT datatype, `e_select2` joins, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows.

Dependency closure:

- No new support component is needed.
- The tests reuse existing `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteGroupedAggregate`, `SQLiteSelectExpression`, `SQLiteCoreScalarFunction`, and hydrated upstream SQLite source truth.
