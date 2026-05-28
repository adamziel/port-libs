# SQL Planner STAT4 Expression Partial Current Source Next245

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Plan`, layered on the accepted next242 histogram-counter proof. It validates that every current `sqlite_stat4` sample rowid still anchors to a current partial-index row whose recomputed `lower(option_name)` key and `blog_id` match the sample payload.

Behavior covered:

- admits the current-source partial expression index only after sample rowids resolve to current partial rows;
- rejects stale rowid, stale expression-key, stale blog-id, and deleted-anchor sample payloads;
- appends a planner cursor validation opcode for sample-anchor proof replay;
- keeps the slice separate from accepted next242 `neq/nlt/ndlt` histogram counter validation, expression `ORDER BY`, range-cost, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner work.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Test.php`
- focused result: `1 test files, 80 assertions, 0 failures`

Dependency closure: no new support component needed; this reuses current-source STAT4 expression partial rows, planner fences, and row-array materialization already present in the lane.
