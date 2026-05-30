# libsqlite json aggregate window current-next71

## Behavior

Adds unit-aware JSON aggregate window frame helpers for `json_group_array`,
`jsonb_group_array`, `json_group_object`, and `jsonb_group_object` over bounded
native PHP row arrays.

Covered behavior:

- `ROWS`, `GROUPS`, and numeric `RANGE` current/next frame membership.
- Peer-group handling for duplicate `ORDER BY` keys.
- `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` after frame
  membership and before aggregate input.
- SQLite-style FILTER truthiness after exclusion.
- JSON subtype and JSONB value preservation in text and JSONB aggregate output.

## Evidence

Focused test command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowCurrentNext71Test.php
Focused test run: 1 selected test files (root lock skipped)
40 PASS lines
1 test files, 46 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-aggregate-window-current-next.php --self-test
application-json-aggregate-window-current-next self-test passed
```

## Non-overlap

This slice avoids accepted JSON object aggregate/window DISTINCT/ORDER/FILTER
coverage, JSON table cursor/source/hidden/visible constraint work, recursive
JSON SELECT materialization, and JSONB CHECK admission. It is scoped to
aggregate window frame-unit semantics for current/next frames.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP JSON,
JSONB, JSON subtype, and TestRunner support.
