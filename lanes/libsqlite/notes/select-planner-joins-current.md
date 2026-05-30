# libsqlite-select-planner-joins-current

- Added current SELECT join-planner fence behavior to `SQLiteJoinOrderPlan`: `CROSS JOIN` sources and outer-join preserved sides are no longer reordered just because the nullable/right side has a cheaper standalone index lookup.
- Added focused coverage for inner join cost reordering versus fenced `CROSS`, `LEFT`, `RIGHT`, and `FULL` joins, including CROSS metadata without equality columns.
- Added Application smoke `application-select-planner-join-fences.php` for copied `wp_posts` / `wp_postmeta` and `wp_options` diagnostics.
- Non-overlap: this does not repeat accepted SELECT JOIN row production, JSON table source/cursor work, expression `ORDER BY`, GROUP BY SQL text, or range-cost expression-index work. It is planner metadata for non-reorderable join operators.
- Dependency closure: no new support component needed; this reuses native sqlite_stat1 planner metadata and the existing PHP join-order planner.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJoinOrderPlan.php && php -l lanes/libsqlite/tests/SQLiteSelectPlannerJoinFencesCurrentTest.php && php -l lanes/libsqlite/examples/application-select-planner-join-fences.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectPlannerJoinFencesCurrentTest.php`
- `php lanes/libsqlite/examples/application-select-planner-join-fences.php`
- `git diff --check -- lanes/libsqlite`
