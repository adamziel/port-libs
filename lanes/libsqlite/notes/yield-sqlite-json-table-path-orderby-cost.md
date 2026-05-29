# JSON Table Path ORDER BY Cost Current Source Next131

## Scope

Adds a bounded current-source JSON table planner profile for the composite
case where `json_tree()` / `json_each()` has a visible `path` constraint and an
`ORDER BY` list. The new profile reuses accepted path constraint pushdown and
partial order-cost metadata, then records the combined path scan signature,
ORDER BY prefix/suffix, suffix-sort requirement, effective cost, path/rowid
tape, and current/next replan reasons.

This does not repeat accepted standalone path pushdown (`next123`), hidden
path/rowid cost (`next126`), hidden path ORDER BY (`next128`), or nested hidden
cost (`next129`). It covers the narrower planner handoff where copied
`wp_options` JSON settings change the path rowset and ORDER BY suffix cost in
one source transition.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTablePathOrderByCostTest.php`
  - `1 test files, 56 assertions, 0 failures`
  - `56` PASS lines

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-json-table-path-orderby-cost.php`
  emits copied `wp_options` plugin-rule JSON path/order cost diagnostics,
  including the current/next ordered path tape, effective costs, reader policy,
  and replan reasons.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON
path, JSON table, JSON tree, residual predicate, and planner-cost helpers.

## Next

Continue JSON table work toward non-overlapping dynamic join/planner behavior
or malformed JSONB planner edges. Avoid repeating accepted visible/hidden
constraint extraction, JSON table SELECT source/cursor behavior, and this
path ORDER BY cost profile.
