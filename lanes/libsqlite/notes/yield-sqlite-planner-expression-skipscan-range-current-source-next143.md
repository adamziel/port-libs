# SQLite planner expression skip-scan range current-source next143

Status: focused PHP behavior growth for the assigned expression skip-scan range
current-source planner slice.

Behavior: adds `SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan`
to fence stale prepared expression skip-scan plans when the current source
changes lower/upper range bounds, upper-bound inclusiveness, or collation. The
plan rewrites the expression range terms to the current source, classifies
range-admitted/rejected/stable rowids, records per-prefix loop deltas, and emits
a cursor tape beginning with `ReprepareIfRangeFenceStale`.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-planner-expression-skipscan-range-current-source-next143.php --self-test`
  - `wordpress-planner-expression-skipscan-range-current-source-next143 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionSkipScanRangeCurrentSourceNext143Test.php`
  - `1 test files, 58 assertions, 0 failures`

Dashboard movement: `phpPass +58` for a new focused test file. Mapped upstream
coverage remains conservative because this is additive behavior over already
mapped planner/skip-scan inventory rather than a newly claimed manifest row.

Non-overlap: avoids partial expression skip-scan next129, expression covering
next132, STAT4 stale-source next137, partial predicate changes next139,
covering partial range next131, expression-index range-cost ranking, SQL
expression ORDER BY, JSON table planner/source/cursor work, WAL/VFS/B-tree
clusters, and accepted skip-scan STAT4/order surfaces. The new surface is only
the current-source range-fence update for expression skip-scan cursors.

Dependency closure: no new support component is needed; the slice reuses native
PHP expression skip-scan materialization, partial predicate proof, STAT4 loop
evidence, and current-source fence conventions.
