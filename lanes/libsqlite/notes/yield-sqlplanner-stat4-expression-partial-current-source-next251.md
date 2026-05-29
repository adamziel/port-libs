# STAT4 Expression Partial Current Source Next251

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a current-source reuse fence for STAT4 partial expression indexes. After the accepted next247 boundary-peer validation admits a yielded row window, next251 verifies that every yielded rowid has a current `stat4ExpressionPayloads` covering image and that each requested covering column still matches the current table row.

The WordPress path is copied `wp_options` planning with `lower(option_name)` partial expression indexes over autoloaded plugin settings. This catches stale covering payload reuse after an option value or timestamp changes without changing the expression key.

## Evidence

Local verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext251Test.php` => `1 test files, 86 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next251.php` => ready status `stat4-expression-partial-current-source-next251-ready`, blocked status `requires-current-source-stat4-covering-payload-reprepare`
- PHP lint for changed PHP files: passed for plan, test, and example files
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`
- `git diff --check -- lanes/libsqlite`: passed

## Non-Overlap

Avoids accepted next247 boundary peer validation, next246/next247 STAT4 current-source planning, expression `ORDER BY`, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only validates covering payload freshness for yielded STAT4 partial expression-index rows.

## Dependency Closure

No new support component is needed. The slice reuses the existing STAT4 expression partial planner chain and adds a bounded PHP payload-freshness fence.
