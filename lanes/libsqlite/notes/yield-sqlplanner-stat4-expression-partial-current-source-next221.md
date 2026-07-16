# SQL Planner STAT4 Expression Partial Current Source Next221

## Behavior

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
- Composes accepted next217 current/next yield continuity for current-source STAT4 partial expression indexes.
- Adds a current STAT4 sample-window fence for yielded cursor pages.
- Verifies yielded row expression keys remain bracketed by current `sqlite_stat4` samples, descending scan sample positions do not regress, rejected rows force reprepare, and the cursor program appends `RecheckStat4SampleWindowYield` only when the sample-window proof is complete.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext221Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next221.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext221Test.php`
- Result: `1 test files, 65 assertions, 0 failures`

## Application Smoke

- `application-sqlplanner-stat4-expression-partial-current-source-next221.php` models copied `wp_options` plugin scans that reuse a current-source partial expression index across cursor yields only after the yielded page and lookahead row stay inside current STAT4 sample windows.

## Non-overlap

Avoids accepted next217 current/next yield continuity, next213 LIKE case checks, next212 grouped LIKE proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only proves yielded partial-expression rows remain bracketed by current-source STAT4 samples.

## Dependency Closure

No new support component is needed. The implementation reuses native current-source STAT4 expression partial planner fences and adds bounded PHP metadata for cursor-yield sample-window validation.
