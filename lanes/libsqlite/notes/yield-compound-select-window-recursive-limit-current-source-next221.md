# Compound SELECT Window Recursive LIMIT Current Source Next221

## Behavior

- Adds a bounded current-source/next-source plan for compound SELECT SQL that combines:
  - a recursive CTE with ordered `LIMIT ... OFFSET` queue exhaustion,
  - aggregate window output from `max(...) OVER (...)` and `sum(...) OVER (...)`,
  - `UNION ALL` arms fenced through `INTERSECT`,
  - an `EXCEPT` tail, and
  - final compound `ORDER BY ... LIMIT ... OFFSET` admission.
- The WordPress smoke models copied `wp_options` import previews where staged option rows shift aggregate window metrics and stale cursor tokens must be rejected before continuing a paged preview.

## Evidence

- Focused test command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext221Test.php`
- Result:
  - `1 test files, 345 assertions, 0 failures`
  - `63` focused PASS lines.

## Non-Overlap

- Avoids accepted compound SELECT row composition, grouped SELECT text, expression-limit next190, lead/nth_value next206, rank/dense_rank next217, and older zero-limit/EXCEPT-only recursive window slices.
- Does not touch JSON, WAL, B-tree, VFS, PRAGMA, trigger, or encoding surfaces.

## Dependency Closure

- No new support component is needed. The slice reuses existing lane-local SELECT SQL compound execution, recursive CTE queue tracing, aggregate window dispatch, compound `INTERSECT`/`EXCEPT`, current-source token fencing, and final LIMIT helpers.
