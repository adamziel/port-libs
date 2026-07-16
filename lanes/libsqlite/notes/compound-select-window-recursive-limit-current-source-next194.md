# compound-select-window-recursive-limit-current-source-next194

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE LIMIT/OFFSET rows and per-arm window values feed chained `INTERSECT`/`EXCEPT` membership before final compound LIMIT/OFFSET.

Behavior covered:

- `WITH RECURSIVE` queue `LIMIT/OFFSET` trace is preserved while the compound arms are evaluated.
- Window output is computed before `INTERSECT`/`EXCEPT` membership decisions.
- Final `ORDER BY ... LIMIT ... OFFSET ...` is applied after membership.
- A next-source copied `wp_options` autoload row changes the admitted membership and invalidates the current-source token fence.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext194Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 254 assertions, 0 failures
# 67 PASS lines

php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next194.php --self-test
# application-compound-select-window-recursive-limit-current-source-next194 self-test passed
```

Expected dashboard movement: `phpPass +67` from the new focused test file. `benchmarkDenominator.mapped` remains `618 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, INTERSECT/EXCEPT, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next189 token-fence coverage, next190 expression LIMIT/OFFSET coverage, next176 INTERSECT lag/lead recursive LIMIT coverage, accepted SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is window-valued compound `INTERSECT`/`EXCEPT` membership before a final LIMIT/OFFSET current-source boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue tracing, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
