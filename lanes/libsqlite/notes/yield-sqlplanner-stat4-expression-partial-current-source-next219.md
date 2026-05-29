# SQL Planner STAT4 Expression Partial Current Source Next219

## Behavior

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
- Composes accepted next217 current/next STAT4 partial-expression yield admission.
- Adds a duplicate expression-key peer-run boundary fence for paged current-source cursors.
- Verifies a page that stops inside repeated `lower(option_name)` peers records the full boundary peer run, the peer rowids already on the page, the remaining peer rowids after the page, and appends `RecheckCurrentSourceStat4PeerRun` only when the next row continues the same peer run.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext219Test.php`
- Result: `1 test files, 72 assertions, 0 failures`

## WordPress Smoke

- `wordpress-sqlplanner-stat4-expression-partial-current-source-next219.php` models copied `wp_options` plugin scans that yield a current-source STAT4 partial `lower(option_name)` cursor only after duplicate `plugin_forms` peers are fenced, so import previews resume at the next peer rowid instead of skipping or duplicating options.

## Non-overlap

Avoids accepted next217 current/next page lookahead, next212 grouped LIKE proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only proves duplicate expression-key peer runs at a current-source STAT4 partial expression cursor page boundary.

## Dependency Closure

No new support component is needed. The implementation reuses native current-source STAT4 expression partial planner fences and adds bounded PHP metadata for duplicate-key cursor-yield continuation.
