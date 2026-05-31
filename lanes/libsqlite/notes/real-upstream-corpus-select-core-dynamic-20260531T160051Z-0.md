# real-upstream-corpus-select-core-dynamic-20260531T160051Z-0

- Lane: libsqlite
- Accepted base: `b396f617ce3725e2a3fde790e5dc3841675ab023`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported section: `e_select-4.13`, `EVIDENCE-OF: R-55403-13450`
- New focused PASS growth: `+1002` TestRunner PASS cases, moving lane-local `phpPass` from `3137763` to `3138765`.

## Behavior

This slice ports the e_select HAVING rule that aggregate and non-aggregate
HAVING terms may reference expressions that are not present in the result set.
The red-first check found that:

```sql
SELECT up||down FROM c1 GROUP BY (down<5) HAVING max(down)<10
```

returned `x1` locally but upstream `e_select-4.13.1.4` expects `x4`. The
executor was using the first row in each explicit group for bare projection
source values even when a `min()` or `max()` aggregate outside the projection
identified a different representative row.

The patch threads parser-discovered `min()` and `max()` aggregate expressions
from SELECT/HAVING/ORDER terms into grouped summary planning, then chooses the
source row that supplies the first such min/max aggregate when materializing
bare source columns. The same sampling hook is applied to implicit aggregate
queries so future aggregate-expression carriers share the same behavior.

## Non-Overlap

Owns only `e_select.test` `e_select-4.13` HAVING expression behavior and
min/max representative source-row selection. It avoids accepted GROUP BY
collation, aggregate wildcard, empty aggregate, DISTINCT/ALL, compound
core/order, LIMIT datatype, e_select2 joins, JSON table, WAL, VFS, B-tree,
PRAGMA, and runner-metadata slices.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql`,
`SQLiteSelectQuery`, `SQLiteGroupedAggregate`, `SQLiteSelectExpression`, and
the hydrated upstream SQLite corpus file.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectHavingMinMaxDynamic20260531T160051ZTest.php`
  - `1 test files, 29009 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectHavingMinMaxDynamic20260531T160051ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectAggregateWildcardDynamic20260531T115945ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectEmptyAggregateDynamic20260531T100625ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateGroupDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect5AggregateDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `7 test files, 118382 assertions, 0 failures`
- Root harness: not run - isolated micro-slice.
