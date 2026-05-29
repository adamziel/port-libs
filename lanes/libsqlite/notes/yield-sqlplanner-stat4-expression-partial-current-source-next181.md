# sqlplanner-stat4-expression-partial-current-source-next181

## Behavior

Adds a focused current-source planner slice for STAT4 expression partial indexes whose partial predicate is an OR. SQLite may use a partial index with predicate terms like `autoload='yes' OR option_name='siteurl'` when a query WHERE clause has a matching AND term. The new helper proves that OR implication, adapts the accepted next178 STAT4 expression ORDER fence, and rechecks the matched OR term in the cursor program before producing rows.

WordPress path: copied `wp_options` option-name range scans over `lower(option_name)` can use a partial expression index for autoloaded plugin options after schema/stat4 changes, while excluding `autoload='no'`, theme, and other-blog rows.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext181Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next181.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext181Test.php`
  - `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next181.php`
  - status `stat4-expression-partial-current-source-next181-ready`
  - selected source `current`
  - matched rowids `[10,20,40,30]`

## Status Delta

- Expected focused PHP PASS-line movement after clean integration: `85432 -> 85503` (`+71`).
- Mapped upstream coverage remains `614 / 1589`; this slice adds focused PHP behavior coverage without claiming a new manifest-backed upstream row.

## Non-Overlap

This extends accepted next178 current-source STAT4 expression partial ORDER-fence behavior with OR-predicate implication. It avoids accepted range-cost ranking, SQL expression `ORDER BY`, JSON hidden/visible constraints, next178 AND-only partial proof, batch166 STAT4 expression partial-index behavior, and the queued suite/schema/VFS rebase surfaces.

## Dependency Closure

No new support component is needed. The slice reuses lane-local expression normalization, STAT4 sample fences, current-source invalidation metadata, and partial predicate proof diagnostics.
