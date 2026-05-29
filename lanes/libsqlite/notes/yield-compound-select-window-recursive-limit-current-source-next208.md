# compound-select-window-recursive-limit-current-source-next208

## Behavior

- Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` for a disjoint compound SELECT current-source boundary:
  `WITH RECURSIVE` queue `ORDER BY ... LIMIT/OFFSET`, `rank()` output, `dense_rank()` partition output, `UNION ALL`, `EXCEPT`, final `ORDER BY` and `LIMIT/OFFSET`, and stale cursor rejection.
- WordPress smoke: `examples/wordpress-compound-select-window-recursive-limit-current-source-next208.php` models copied `wp_options` preview rows where next-source autoload rows shift the ranking and final LIMIT boundary after an `EXCEPT` tail.
- Dependency closure: no new support component is needed; this reuses native SELECT SQL compound execution, recursive queue tracing, ranking window dispatch, EXCEPT membership, current-source tokens, and final LIMIT helpers.
- Non-overlap: avoids accepted next206 lead/nth_value INTERSECT fencing, next203 lag/last_value EXCEPT fencing, next202 ntile/first_value UNION distinct, next196/195/192/191 compound window variants, and unrelated JSON/WAL/B-tree/VFS clusters.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext208Test.php`
  - `1 test files, 392 assertions, 0 failures`
  - `70` PASS lines
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next208.php`
  - JSON self-test output with status `compound-select-window-recursive-limit-current-source-next208-ready`

## Expected Status Delta

- `phpPass`: `100087 -> 100157` (`+70` focused PASS lines).
- `phpFail`: unchanged at `0`.
- Mapped upstream coverage: unchanged; this is current-source focused PHP behavior coverage, not a new manifest-backed upstream row.
