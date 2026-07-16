# SQL planner STAT4 expression partial current-source next153

Status: focused PHP behavior growth for a STAT4 expression partial-index
current-source sample fence.

Behavior: adds the stable
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4SampleCurrentSourceFence()`
entry point. It composes the existing partial-predicate drift planner while
publishing a non-numbered STAT4 sample fence that proves refreshed STAT4
samples and current rowids are used after schema/stat4/index signatures change,
and stale prepared rowids are blocked before yielding a covering row stream.

Verification:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext153Test.php` failed because the focused path did not exist.
- After: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext153Test.php` -> `1 test files, 29 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-sample-fence.php --self-test`.

Dependency closure: no new support component needed; this reuses lane-local
expression-index STAT4 range planning, partial predicate proof, current-source
row fences, and the focused PHP TestRunner harness.

Non-overlap: avoids accepted next155 partial-predicate drift output,
expression ORDER BY, range-cost ranking, JSON, VFS/WAL, B-tree, and suite
evidence clusters. The production entry point is stable/descriptive and does
not add a numbered helper.
