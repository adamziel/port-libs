# real-upstream-corpus-window-functions-dynamic-20260531T232438Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T232438Z-0`

Base accepted HEAD: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `window1.test` `25.1`, `25.2`, `26.2`, and `26.3`.

## Behavior

This batch covers `row_number() OVER (...)` results produced inside `IN`
subqueries:

- `window1.test 25.1`: a correlated `t1_id + row_number()` subquery cannot
  match the outer `t1_id`.
- `window1.test 25.2`: a row-number-only subquery admits joined rows whose id
  is inside the generated row-number set.
- `window1.test 26.2`: `row_number() OVER ()` produced from a derived source
  is compared by an outer `IN` predicate.
- `window1.test 26.3`: the correlated `WHERE x=c` inside the derived source
  changes the row-number cardinality per outer row.

The PHP port already executed the exact upstream probes on this accepted base,
so this handoff adds focused real-upstream corpus coverage rather than a
production source change.

## Focused Growth

- Added `SQLiteRealUpstreamWindow1SubqueryInDynamic20260531T232438ZTest.php`.
- The file contains 1000 generated dynamic TestRunner cases plus exact upstream
  probes and source-truth guards.
- Verified focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryInDynamic20260531T232438ZTest.php`
  passed with `1 test files, 6010 assertions, 0 failures`.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryInDynamic20260531T232438ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryInDynamic20260531T232438ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This owns only `window1.test` `25.1-25.2` and `26.2-26.3`. It avoids accepted
window planner sort reuse (`23.1-23.6`), alias `ORDER BY`, aggregate row,
range-offset, partition/ranking/value-frame, pushdown, filter/filterfault,
JSON, WAL, VFS, B-tree, PRAGMA, trigger, suite metadata, and generated fake
upstream ids.

## Dependency Closure

No new support component is needed. The batch reuses `SQLiteSelectSql` window
projection, derived-source execution, correlated subquery scope, `IN`
predicate dispatch, and row-array execution against generic application table
names.
