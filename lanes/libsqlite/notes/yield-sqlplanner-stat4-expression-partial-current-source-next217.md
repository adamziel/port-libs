# SQL Planner STAT4 Expression Partial Current Source Next217

## Behavior

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
- Composes accepted next212 current-source STAT4 partial-expression grouped LIKE admission.
- Adds a one-row lookahead yield fence for resumable current-source cursors.
- Verifies the visible page matches the lookahead prefix, the resume rowid and next rowid are recorded, peer rowids remain ordered under equal expression keys, and the cursor program appends `RecheckCurrentNextStat4Yield` only when the current/next stream is proven.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next217.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext217Test.php`
- Result: `1 test files, 68 assertions, 0 failures`

## WordPress Smoke

- `wordpress-sqlplanner-stat4-expression-partial-current-source-next217.php` models copied `wp_options` plugin scans that reuse a current-source STAT4 partial expression index across cursor yields only after the page and next lookahead row preserve the current expression-key stream.

## Non-overlap

Avoids accepted next210 duplicate peer rowid fences, next211 seek-window fences, next212 grouped LIKE arm proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only proves current/next cursor yield continuity for an already admitted current-source STAT4 partial expression stream.

## Dependency Closure

No new support component is needed. The implementation reuses native current-source STAT4 expression partial planner fences and adds bounded PHP metadata for cursor-yield lookahead.
