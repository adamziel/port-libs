# JSON table generated hidden rowid cost current source next142

Added `SQLiteJsonTablePlan::currentSourceGeneratedHiddenRowidCostNext142()` for the intersection of generated hidden JSON constraints and hidden rowid aliases (`rowid`, `_rowid_`, `oid`) over a pinned current `json_tree()` source.

The slice composes the accepted generated hidden-cost planner from next136 with rowid alias residuals. It records rowid constraint signatures, generated-vs-rowid matched counts, intersected rowids/fullkeys, a generated rowid tape, effective cost, cost class, current/next transitions, and replan reasons.

Application relevance: copied `wp_options` plugin settings can preview a generated `priority`/`enabled` filter while keeping a hidden rowid point seek pinned to the current JSON source, even when a next import adds sibling rules.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenRowidCostCurrentSourceNext142Test.php`
- Result: `1 test files, 65 assertions, 0 failures`

Dependency closure: no new support component is needed; this reuses the lane-local JSON table current-source planner, generated hidden-cost planner, rowid alias constraints, JSON path evaluator, and JSON tree row production.

Non-overlap: this does not repeat accepted JSON table visible/hidden constraint extraction, SELECT source/cursor wiring, hidden path rowid current-source next140, generated hidden cost next136, queued generated hidden residual-cost next141, or accepted JSON aggregate/window behavior. The new behavior is the combined generated-hidden plus rowid-alias cost/current-source intersection.
