# json-table-hidden-rowid-order-current-source-next135

Status: focused PHP behavior growth for JSON table hidden rowid ORDER BY current-source handoff.

This slice adds `SQLiteJsonTablePlan::currentSourceHiddenRowidOrder()`. It composes the accepted hidden-rowid current-source planner with ORDER BY profiling so a prepared `json_tree()` scan can distinguish stable hidden rowid tie-break order from source JSON changes that alter generated order keys, sorted rowids, and reprepare reasons.

WordPress path: `wordpress-json-table-hidden-rowid-order.php` models copied `wp_options` plugin rule JSON where `ORDER BY atom DESC, rowid` keeps equal priority leaves in hidden rowid order while the next source inserts a new rule with the same priority.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidOrderTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-json-table-hidden-rowid-order.php --self-test
wordpress-json-table-hidden-rowid-order self-test passed
```

Dashboard delta: `phpPass` moves from `56681` to `56740` for the 59 verified PASS lines. Mapped upstream coverage is unchanged because this is current-source PHP behavior over already mapped JSON table hidden rowid and ORDER BY planner surfaces, not a newly hydrated upstream inventory row.

Non-overlap: avoids accepted JSON table hidden rowid source next94, constraint cost/order next113, hidden path/order next128, hidden/visible constraint extraction, JSON table SELECT source/cursor work, lateral rowid behavior, and batch132 JSON generated ORDER BY coverage. The new surface is hidden rowid alias participation as a tie-breaker in ORDER BY current/next source profiles.

Dependency closure: no new support component is needed. The slice reuses lane-local JSON table rows, hidden rowid aliases, residual constraints, and current-source planner primitives.

Next task: continue JSON work only on a non-overlapping planner/JSONB/dynamic-source edge with focused tests, or pivot to another under-owned current-source closure bucket.
