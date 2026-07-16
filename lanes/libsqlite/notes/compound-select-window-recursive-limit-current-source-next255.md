# compound-select-window-recursive-limit-current-source-next255

## Behavior

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on accepted next250 next-page admission. The new continuation fence binds the admitted current page, held next page, current/next window metrics, recursive emitted/skipped lineage, and spillover labels before next-source rows may resume after the final compound `LIMIT/OFFSET`.

Application path: `application-compound-select-window-recursive-limit-current-source-next255.php` models copied `wp_options` retry scans where a plugin option enters the held next page. The next source remains held until continuation acknowledgements prove both the current recursive/window page and next candidate page match the replay cursor.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next255.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Test.php`
  - Result: `1 test files, 466 assertions, 0 failures`
  - PASS lines: `86`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next255.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This slice extends accepted next250 admission-only behavior with a separate continuation resume acknowledgement set. It avoids accepted next250 next-page admission, next248/next249 promotion fences, next252 row-value/window work, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, and suite evidence clusters.

## Dependency Closure

No new support component is needed. The patch reuses native `SQLiteSelectSql`, compound SELECT row production, recursive CTE LIMIT/OFFSET traces, per-arm window output, and accepted next246/next250 current-source tokens.
