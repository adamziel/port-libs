# sqlplanner-stat4-expression-partial-current-source-next184

## Behavior

Adds a focused current-source planner slice for STAT4 expression partial indexes whose partial predicate contains an `IN` list. SQLite may use a partial index with a predicate like `autoload IN ('yes','auto-on','eager')` when the query WHERE clause constrains `autoload='yes'`. The new helper proves that implication, adapts the accepted next178 STAT4 expression partial current-source fence, and inserts a cursor recheck for the matched `IN` value before producing covering rows.

Application path: copied `wp_options` option-name range scans over `lower(option_name)` can keep using a partial expression index for autoloaded plugin options after schema/stat4 changes, while excluding `autoload='no'`, theme, and other-blog rows.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext184Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next184.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext184Test.php`
  - `1 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next184.php`
  - status `stat4-expression-partial-current-source-next184-ready`
  - selected source `current`
  - matched rowids `[11,21,41,31]`

## Status Delta

- Expected focused PHP PASS-line movement after clean integration: `86745 -> 86823` (`+78`).
- Mapped upstream coverage remains `615 / 1589`; this slice adds focused PHP behavior coverage without claiming a new manifest-backed upstream row.

## Non-Overlap

This extends accepted next178 current-source STAT4 expression partial scan behavior with partial-index `IN`-predicate implication. It avoids next181 OR-predicate implication, accepted STAT4 expression partial next181 behavior, range-cost ranking, SQL expression `ORDER BY`, JSON constraints/source/cursor work, WAL/VFS transaction slices, and B-tree freeblock/freelist surfaces.

## Dependency Closure

No new support component is needed. The slice reuses lane-local expression normalization, STAT4 sample fences, current-source invalidation metadata, and partial predicate proof diagnostics.
