## JSON Table Generated Path Rowid Cost Current Source Next271

Implemented `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext271()` as an additive generated-path rowid cost-selection layer over the existing next224 yield guard and next220 xRowid profiles.

Behavior:
- Keeps a pinned current-source `json_tree()` generated-path rowid point at estimated cost `1` when xCurrent, xRowid, alias, fingerprint, and rowid observations agree.
- Forces changed next-source imports through the high-cost reprepare path when generated path/source generation changes invalidate the reusable current-source rowid point.
- Records transition reasons for source, rowset, admission, index, and cost changes so older JSON-table rowid/cost compatibility handoffs can be repaired without overlapping accepted JSON table cursor/source/hidden/visible constraint work.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext271Test.php`
- Result: `1 test files, 58 assertions, 0 failures`
- PASS-line delta: `+58`

Application smoke:
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next271.php --self-test`
- Result: `application-json-table-generated-path-rowid-cost-current-source-next271 self-test passed`

Non-overlap:
- Avoids accepted parser-level JSON table SELECT source/cursor wiring, hidden constraints, visible constraints, host joins, LIMIT/OFFSET, window ranking, and malformed JSON planner clusters.
- This slice is limited to generated-path rowid current-source cost selection for the isolated rowid/cost repair family named by the session.

Dependency closure:
- No new support component needed; reuses native JSON table generated-path, rowid xCurrent/xRowid, yield-guard, and current-source cost profile machinery.
