# SQLite Compound SELECT Window Recursive LIMIT Current Source Next160

## Behavior

- Adds a lane-local current-source slice for recursive CTE queue `LIMIT offset,count` syntax inside a compound SELECT.
- The focused SQL composes recursive queue offset/count admission, per-arm window `row_number()` evaluation, `UNION ALL`, final compound `ORDER BY`, and final `LIMIT/OFFSET`.
- The Application smoke models copied `wp_options` import previews where recursive staging rows and autoloaded option rows are ranked before a bounded import preview.

## Non-Overlap

- Avoids accepted next139/141/143/144/157 compound clusters by targeting recursive comma-LIMIT queue offset/count syntax, not generic recursive LIMIT, EXCEPT/INTERSECT retention, queue ORDER BY, or accepted compound row composition.
- Avoids accepted SELECT comma-LIMIT by applying the syntax to recursive CTE queue admission inside a compound/window SELECT.
- Avoids JSON, B-tree, WAL, VFS, trigger, PRAGMA, and encoding accepted surfaces.

## Dependency Closure

- No new support component is needed. This reuses the lane-local SELECT SQL parser/executor, recursive CTE queue trace, compound SELECT combiner, and window evaluation primitives.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext160Test.php`
  - `1 test files, 215 assertions, 0 failures`
  - `69` PASS lines
- `php lanes/libsqlite/examples/application-compound-recursive-comma-limit-window-next160.php`
  - passed and emitted `compound-select-window-recursive-limit-current-source-next160-ready`

## Expected Dashboard Delta

- `phpPass`: `70891 -> 70960` after clean integration of this isolated slice.
- Mapped coverage remains `608 / 1589`; no new upstream-manifest denominator row is claimed.
