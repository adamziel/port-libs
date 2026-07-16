# JSON Aggregate DISTINCT ORDER Window Current-Next 75

## Behavior

Adds bounded native PHP coverage for `json_group_array(DISTINCT value ORDER BY key)`
over current/next-style window frames. The implementation reuses the existing
`ROWS` / `GROUPS` / `RANGE` frame engine, then applies SQLite-style distinct
selection after frame ordering so the first ordered instance of each value wins.

This is intentionally disjoint from accepted JSON object aggregate/window
coverage, JSON table cursor/source wiring, JSON visible/hidden constraint
pushdown, and parser-level aggregate FILTER/ORDER coverage.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctOrderWindowCurrentNext75Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 49 assertions, 0 failures
40 PASS lines
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-aggregate-distinct-order-window-current-next75.php --self-test
application-json-aggregate-distinct-order-window-current-next75 self-test passed
```

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON,
JSONB, aggregate-state, and window-frame primitives.

## Next

Parser-level admission for `json_group_array(DISTINCT ... ORDER BY ...) OVER`
can be wired later once the SELECT SQL window parser grows that surface.
