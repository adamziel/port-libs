# Aggregate FILTER ORDER DISTINCT Edge Next13

Slice: `yield-sqlite-aggregate-filter-order-distinct-edge-next13`

Behavior added:

- Models SQLite `group_concat(DISTINCT value ORDER BY key) FILTER (WHERE predicate)` edge behavior in `SQLiteTextAggregate`.
- Preserves upstream ordering for duplicate DISTINCT values by keeping the first surviving row's ORDER BY key, then sorting the distinct rows. A local `sqlite3` oracle check for `VALUES('a',5,1),('a',1,1),('b',3,1)` returned `b,a`.
- Applies FILTER before DISTINCT and ORDER BY, skips NULL aggregate values, preserves storage-class distinctness for text/blob/integer/real values, and keeps SQL truthiness for filter expressions.

Verification:

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAggregateFilterOrderDistinctEdgeNext13Test.php`
- Expected focused delta: 31 PASS lines from the new lane-scoped test file.
- Application smoke: `php lanes/libsqlite/examples/application-aggregate-filter-order-distinct-edge.php --self-test`

Non-overlap:

- Does not repeat accepted JSON aggregate/window object coverage, grouped SELECT SQL text, expression ORDER BY, JSON table constraints/cursors/sources, VFS writer/lock/sync/rollback paths, WAL byte truncation/checkpoint transaction, B-tree page moves/root collapse/interior merge/overflow freelist release, Unicode GLOB ranges, or SELECT subquery/comma LIMIT clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP aggregate helper and scalar/BLOB value support.
