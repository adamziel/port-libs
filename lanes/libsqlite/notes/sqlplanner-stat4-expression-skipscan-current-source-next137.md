# sqlplanner-stat4-expression-skipscan-current-source-next137

This slice adds bounded current-source planner evidence for expression
skip-scan plans using STAT4 samples. It composes the existing expression
skip-scan planner and records the missing stale-source behavior:

- stale prepared statements select the current source when schema/stat4
  generations diverge;
- per-prefix STAT4 current/next sample deltas are exposed for skip-scan loops;
- stale/current rowid deltas show newly admitted rows after ANALYZE refresh;
- the cursor tape records the `ReprepareIfStale`, `SeekScan`, range recheck,
  covering-column read, and next/prev opcodes.

WordPress path:
`php lanes/libsqlite/examples/wordpress-planner-stat4-expression-skipscan-current-source-next137.php`
models copied `wp_options` plugin rows where a refreshed STAT4 source admits a
new `PLUGIN_SECURITY` option through `lower(option_name)` skip-scan.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionSkipScanCurrentSourceNext137Test.php
php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-skipscan-current-source-next137.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionSkipScanCurrentSourceNext137Test.php
php lanes/libsqlite/examples/wordpress-planner-stat4-expression-skipscan-current-source-next137.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 64 assertions, 0 failures`.

Non-overlap: avoids accepted partial expression skip-scan next129, covering
skip-scan next125/next127/next132, STAT4 expression covering range next128,
partial/range current-source next124/next131, expression-index range-cost,
SQL expression ORDER BY, JSON table, VFS/WAL, and B-tree clusters. The new
surface is STAT4 stale-source selection and current/next deltas for expression
skip-scan loops.

Dependency closure: no new support component is needed. The patch reuses
native PHP expression skip-scan, STAT4 per-prefix samples, current-source
fences, and covering cursor diagnostics.
