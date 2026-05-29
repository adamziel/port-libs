# compound-except-window-recursive-limit-current-source-next161

Status: focused PHP behavior growth for parser-level compound SELECT output where a recursive CTE queue and a WordPress option table arm compute window values before an `EXCEPT` arm removes skip rows, then the final compound `ORDER BY ... LIMIT/OFFSET` determines the current/next yield boundary.

Behavior covered:

- `WITH RECURSIVE` queue rows are generated before the compound set operation.
- Window values in recursive and table arms are evaluated before `EXCEPT`.
- The `EXCEPT` arm removes only exact projected rows, so a changed next-source window rank can make a `skip_%` row survive.
- Final `ORDER BY win, id LIMIT 5 OFFSET 1` is applied after the compound EXCEPT rowset is built.
- Current/next copied `wp_options` rows expose gained/lost labels, changed exclusion diagnostics, and yield-boundary skipped/truncated rows.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNext161Test.php
php lanes/libsqlite/examples/wordpress-compound-except-window-recursive-limit-current-source-next161.php --self-test
php -l lanes/libsqlite/src/SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNext161Test.php
php -l lanes/libsqlite/examples/wordpress-compound-except-window-recursive-limit-current-source-next161.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +62` from the new focused test file. `benchmarkDenominator.mapped` remains `609 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, EXCEPT, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next158/next159 compound recursive/window LIMIT and yield behavior, accepted next139 recursive queue LIMIT window boundary, next148 EXCEPT window LIMIT without this recursive queue/exact-exclusion interaction, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is exact projected `EXCEPT` exclusion after per-arm window values but before the final current/next compound LIMIT boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound EXCEPT, window row-array execution, and result LIMIT/OFFSET machinery.
