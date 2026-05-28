# compound-select-window-recursive-limit-current-source-next246

## Behavior

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan`, extending the accepted next243 replay-ticket fence with a current-source handoff token. The handoff binds:

- recursive LIMIT/OFFSET cursor exhaustion;
- final-page spillover acknowledgement state from next240;
- current-row window replay tickets from next243;
- current and next final labels before next-source exposure.

This keeps a yielded WordPress `wp_options` next-source candidate held until the current compound SELECT source has acknowledged recursive queue, spillover, and window lineage state.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Test.php`
- Result: `1 test files, 617 assertions, 0 failures`
- PASS lines: `80`
- Expected `phpPass`: `125265 -> 125345`

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next246.php --self-test`

## Non-Overlap

This slice extends accepted next243 replay-ticket behavior and next240 spillover-drain behavior with a combined current-source handoff fence. It does not repeat JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, suite evidence, next240 spillover-only, next242 commit-fence, or next243 replay-only clusters.

## Dependency Closure

No new support component is needed. The patch reuses native `SQLiteSelectSql` compound execution, recursive CTE trace support, window output, spillover drain, and replay-ticket metadata.
