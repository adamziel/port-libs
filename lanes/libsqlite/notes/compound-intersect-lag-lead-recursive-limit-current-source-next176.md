# compound-intersect-lag-lead-recursive-limit-current-source-next176

Status: focused PHP behavior growth for parser-level compound `INTERSECT` where a recursive CTE queue uses `LIMIT/OFFSET`, per-arm `lag()` window values are evaluated before set intersection, and the final compound `ORDER BY ... LIMIT ... OFFSET` admits a next-source Application option row.

Behavior covered:
- `WITH RECURSIVE` queue `LIMIT 5 OFFSET 1` skips the anchor row before the recursive arm reaches the compound operator.
- `lag()` markers are computed inside both compound arms before `INTERSECT` compares complete rows.
- Final compound ordering by the window marker applies before `LIMIT 3 OFFSET 1`, so the next copied `plugin_alpha` row crosses the displayed boundary.
- A companion `lead()` current/next diagnostic verifies the same copied `wp_options` source has forward-looking window state for the newly admitted row.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNext176Test.php
```

Result: `1 test files, 255 assertions, 0 failures` with 65 PASS lines.

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-intersect-lag-lead-recursive-limit-current-source-next176.php
```

Result: `application-compound-intersect-lag-lead-recursive-limit-current-source-next176 self-test passed`.

Expected dashboard movement: `phpPass +65` from the new focused test file. `benchmarkDenominator.mapped` remains `613 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Dependency closure: no new support component is needed; this reuses lane-local `SQLiteSelectSql` recursive CTE queue, compound `INTERSECT`, window lag/lead execution, ordering, and final limit machinery.

Non-overlap: avoids accepted next139/next158/next159/next173 compound recursive/window LIMIT variants, next164 row-number `INTERSECT`, next157 `INTERSECT` recursive/window LIMIT, accepted SQL JOIN/GROUP/subquery/ORDER/comma-LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is `lag()` row identity through `INTERSECT` after recursive queue offset and before the final current/next LIMIT boundary.

Next task: keep any future compound work away from this `INTERSECT` lag/current-boundary path unless it wires the same behavior into a broader native VDBE bytecode executor.
