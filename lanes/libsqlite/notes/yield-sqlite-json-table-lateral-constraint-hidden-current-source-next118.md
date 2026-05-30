# JSON table lateral constraint hidden current-source next118

Slice: `json-table-lateral-constraint-hidden-current-source-next118`.

This adds `SQLiteJsonTablePlan::lateralConstraintHiddenCurrentSourceNext118()`
for keyed lateral JSON table scans whose hidden `json` and `root` constraints
come from the current host row. The planner composes the existing hidden
constraint-source current/next behavior with host-key transitions, LEFT JOIN
NULL extension, host row additions/removals, JSONB source-kind changes,
SQL NULL root empty-rowset handling, and per-host constraint value transition
metadata.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralConstraintHiddenCurrentSourceNext118Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures

$ php lanes/libsqlite/examples/application-json-table-lateral-constraint-hidden-current-source-next118.php --self-test
application-json-table-lateral-constraint-hidden-current-source-next118 self-test passed
```

Expected dashboard movement: `phpPass` +64 from the new focused PASS lines
(`45302 -> 45366`). Mapped upstream coverage remains `604 / 1589`; this is
native focused PHP coverage and a Application smoke, not a fresh upstream
testfixture claim.

Non-overlap: avoids accepted JSON table hidden-constraint extraction,
visible-column pushdown, parser-level JSON table SELECT sources, cursor
behavior, host joins, lateral hidden rowid/current-source next105, constraint
cost/order next113, JSON path negative-index work, and JSON aggregate/window
clusters. The new surface is keyed lateral current/next planning where hidden
constraint values are sourced from each host row.

Dependency closure: no new support component is needed. The slice reuses the
existing native JSON table planner, JSONB value handling, hidden
constraint-source planner, and TestRunner only.
