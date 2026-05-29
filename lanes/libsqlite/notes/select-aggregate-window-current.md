# SELECT Aggregate Window Current

## Behavior

- Adds parser-level SELECT SQL execution for numeric/text aggregate window default frames:
  `count`, `sum`, `total`, `avg`, `min`, `max`, and `group_concat`.
- Applies SQLite aggregate-window default framing for those functions:
  whole partition without window `ORDER BY`, and cumulative peer groups when
  `ORDER BY` is present.
- Adds `total()` to the SELECT window dispatch and preserves SQLite's `0.0`
  result for empty or all-NULL frames.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectAggregateWindowCurrentTest.php`
  - `1 test files, 31 assertions, 0 failures`

## WordPress Smoke

- `lanes/libsqlite/examples/wordpress-select-aggregate-window-current.php`
  covers copied `wp_options` diagnostics for cumulative aggregate windows and
  enabled-row `total()` summaries without requiring `ext/sqlite`.

## Non-Overlap

This slice avoids the accepted parser-level grouped SELECT, expression
`ORDER BY`, JSON aggregate window, VFS, WAL, and B-tree clusters. It only
extends the existing unsuffixed SELECT/window executor for aggregate window
defaults and `total()` dispatch.

## Dependency Closure

No new support component is needed. The change reuses the native SELECT SQL,
window frame, numeric aggregate, and `group_concat` helpers already under
`lanes/libsqlite/src`.
