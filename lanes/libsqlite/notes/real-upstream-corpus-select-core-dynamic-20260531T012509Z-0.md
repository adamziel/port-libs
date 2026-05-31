# real-upstream-corpus-select-core-dynamic-20260531T012509Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `select1-6.20` through `select1-6.23`, ticket `#2296`

Behavior ported:

- Compound `UNION` subqueries used as `IN` predicate sources.
- `ORDER BY` inside the compound subquery by ordinal, left result name, and
  compound result alias.
- `LIMIT` on the compound subquery before membership filtering by the outer
  query.

Focused test movement:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelect1CompoundInDynamicTest.php`.
- The file contributes `1001` focused TestRunner PASS cases: one upstream
  source citation case plus `250` dynamic rowsets across the four upstream
  shapes.

Non-overlap:

- This slice does not repeat accepted SELECTE compound ORDER error behavior,
  selectB derived compound flattening, selectF union-copy ordering, selectA
  top-level compound merge ordering, select1-17 derived compound sources, or
  select1-18 correlated BETWEEN coverage.
- It focuses specifically on compound subquery result ordering and limiting
  before an outer `IN` membership test.

Dependency closure:

- No new support component is needed. The existing `SQLiteSelectSql`
  parser/executor, compound SELECT planner, and subquery predicate machinery
  are reused directly.
