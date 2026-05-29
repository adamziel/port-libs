# compound-select-window-recursive-limit-current-source-next206

## Behavior

- Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` for a disjoint compound SELECT current-source boundary:
  `WITH RECURSIVE` queue `ORDER BY ... LIMIT/OFFSET`, `lead()` default output, `nth_value()` frame output, `UNION ALL`, `INTERSECT`, final `ORDER BY` and `LIMIT/OFFSET`, and stale cursor rejection.
- WordPress smoke: `examples/wordpress-compound-select-window-recursive-limit-current-source-next206.php` models copied `wp_options` preview rows where next-source autoload rows shift the `INTERSECT` membership and final LIMIT boundary.
- Dependency closure: no new support component is needed; this reuses native SELECT SQL compound execution, recursive queue tracing, window dispatch, INTERSECT membership, current-source tokens, and final LIMIT helpers.
- Non-overlap: avoids accepted next203 lag/last_value EXCEPT fencing, next196 ntile/first_value UNION distinct, next195 INTERSECT/EXCEPT row_number membership, next192 percent_rank/cume_dist windows, next191 nth_value/ntile/lead value-offset tape, and unrelated JSON/WAL/B-tree/VFS clusters.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext206Test.php`
  - `1 test files, 392 assertions, 0 failures`
  - `70` PASS lines
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next206.php`
  - JSON self-test output with status `compound-select-window-recursive-limit-current-source-next206-ready`

## Expected Status Delta

- `phpPass`: `98594 -> 98664` (`+70` focused PASS lines)
- `phpFail`: unchanged at `0`
- Mapped upstream coverage: unchanged; this is current-source focused PHP behavior coverage, not a new manifest-backed upstream row.
