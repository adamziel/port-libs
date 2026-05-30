# SQLite STAT4 Expression Range Current Source Next104

- Slice: `sqlplanner-stat4-expression-range-current-source-next104`.
- Behavior: expression-index range planning now compares prepared and current
  schema/stat4/index/projection signatures, reparses stale prepared plans, and
  selects current `sqlite_stat4` sample boundaries for a Application
  `wp_options` expression index on `substr(option_name, 1, 12)`.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteStat4ExpressionRangeCurrentSourceNext104Test.php`.
- Application smoke: `php lanes/libsqlite/examples/application-stat4-expression-range-current-source-next104.php`.
- Non-overlap: avoids accepted STAT4 partial-covering/order-covering,
  expression-index range-cost, SELECT expression ORDER BY, JSON table, WAL,
  VFS, and B-tree current-source clusters. This slice is only the current
  source invalidation and STAT4 sample-boundary behavior for expression-index
  range scans.
- Dependency closure: no new support component is needed; the slice composes
  native PHP expression-index metadata and `sqlite_stat4` sample diagnostics.
