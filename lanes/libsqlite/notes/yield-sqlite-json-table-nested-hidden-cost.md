# JSON table nested hidden cost current source next129

Implemented `SQLiteJsonTablePlan::currentSourceNestedHiddenCost()` for
the current/next planner case where a `json_each()` / `json_tree()` cursor is
using hidden `json` and composed hidden `root` arguments from a source row,
while a nested root fragment changes for the next source row.

The helper composes `base_root` plus `nested_path`, reuses the existing
path/rowid hidden-cost planner, then records nested hidden argument tapes,
root depth, scan strategy, composite path/rowid signatures, rowid/fullkey
tapes, hidden estimated cost, current/next transitions, and replan reasons.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableNestedHiddenCostTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-nested-hidden-cost.php
```

Dependency closure: no new support component is needed. This reuses native PHP
JSON table planning, JSONB/subtype validation, hidden argument extraction,
path/rowid cost profiling, and row-array JSON table filtering already present
in the lane.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible
constraint extraction, indexed visible cost, path constraint pushdown, path
hidden rowid cost, nested path planning, nested constraint cost/order next125
and next127, current-source next113/119/121/123/124/126 surfaces, and JSON
aggregate/window work. The new surface is the nested hidden `json/root`
argument cost handoff and transition profile for current-source next129.
