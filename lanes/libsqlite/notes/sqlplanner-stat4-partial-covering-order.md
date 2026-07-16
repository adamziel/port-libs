# SQL planner STAT4 partial-covering current-source next142

- Behavior: `SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan` composes the accepted next135 partial-covering STAT4 row stream and adds current/next ORDER block materialization for duplicate range keys and right-part sort handoff while keeping payload reads on the covering index cursor.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialCoveringOrderTest.php`
- Application smoke: `php lanes/libsqlite/examples/application-stat4-partial-covering-order.php --self-test`
- Assertion/pass delta: 68 new focused TestRunner PASS lines.
- Dependency closure: no new support component needed; this reuses native partial-index proof, STAT4 range planning, and next135 row-stream materialization.
- Non-overlap: avoids accepted next135 row-stream admission, next138 non-partial STAT4 ranges, expression ORDER BY, expression-index range costs, skip-scan, JSON table, WAL, VFS, B-tree, and PRAGMA clusters.
