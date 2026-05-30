# consolidate-final-numbered-planner-stat4-dynamic-20260530T015329Z-1

Scope: planner STAT4 expression-partial consolidation only.

Change:

- Renamed the post-ANALYZE STAT4 sample-window production entrypoint from its
  legacy numbered name to
  `materializePostAnalyzeSampleWindowFence()`.
- Migrated the direct focused test and WordPress smoke to stable filenames and
  the stable production entrypoint.
- Preserved observable planner result metadata: `next167*` result keys, status
  strings, dependency strings, cursor opcodes, detail text, non-overlap text,
  exception wording, and downstream `materializeNext173()` behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPostAnalyzeSampleWindowTest.php`
  passed.
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-post-analyze-sample-window.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPostAnalyzeSampleWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext173Test.php`
  passed: 2 files / 124 assertions / 0 failures.
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-post-analyze-sample-window.php --self-test`
  passed: printed `wordpress-sqlplanner-stat4-expression-partial-post-analyze-sample-window self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  passed: 133 files / 7539 assertions / 0 failures.
- `git diff --check -- lanes/libsqlite`
  passed.

Dependency closure: no new support component is needed; this is a production
entrypoint consolidation over the existing STAT4 expression-partial
current-source planner helpers.

Non-overlap: this cleanup only touches the planner STAT4 post-ANALYZE
sample-window handoff and its downstream duplicate-key fanout caller. It
avoids pager, WAL/VFS, B-tree, JSON, compound SELECT, trigger, PRAGMA, and UTF
behavior clusters.
