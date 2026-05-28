# compound-select-window-recursive-limit-current-source-next250

## Behavior

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext250Plan`, extending the accepted next246 current-source handoff with a next-page admission fence. The fence binds:

- recursive CTE `LIMIT/OFFSET` exhaustion;
- current-source handoff acknowledgements from next246;
- current and next final compound pages after `UNION ALL` / `INTERSECT` / `EXCEPT`;
- window replay and spillover lineage before next-source exposure.

This keeps a WordPress `wp_options` import/retry scan from exposing the next compound page until the current recursive/window source page has a matching resume token and admission acknowledgements.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext250Test.php`
- Result: `1 test files, 417 assertions, 0 failures`
- PASS lines: `79`
- Expected `phpPass`: `128615 -> 128694`

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next250.php --self-test`

## Non-Overlap

This slice extends accepted next246 handoff behavior with a distinct next-page admission fence. It does not repeat next246 handoff-only, next243 replay-only, next240 spillover-only, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, or suite evidence clusters.

## Dependency Closure

No new support component is needed. The patch reuses native `SQLiteSelectSql` compound execution, recursive CTE trace support, window output, spillover drain, replay-ticket metadata, and current-source handoff tokens.
