# SQLite JSON each indexed regression

- Micro-slice: `consolidate-final-numbered-json-table-dynamic-20260529T234525Z-2`.
- Behavior: parser-level comma-form FROM sources now lower to CROSS JOIN row production, allowing upstream-style `FROM user, json_each(user.phone)` and `json_tree(row.json, row.root)` dynamic virtual-table scans to execute through the existing JSON table planner.
- Consolidation: renamed the dynamic JSON table regression test/note to stable unsuffixed names while preserving the same assertions and stable WordPress smoke scenario.
- Focused test delta: unchanged by consolidation; `SQLiteJsonEachIndexedRegressionTest.php` still provides the same `26` TestRunner PASS cases.
- Expected `phpPass` delta: unchanged; this patch renames the existing focused coverage instead of adding new assertions.
- `benchmarkDenominator.mapped` unchanged; this is focused PHP executor coverage inspired by upstream JSON comma-join regression shape, not a fresh hydrated upstream runner mapping.
- Non-overlap: avoids accepted JSON table cursor/source hidden/visible constraint work, commuted hidden constraints, JSON host joins, expression ORDER BY, SELECT subqueries, Unicode GLOB, VFS/WAL transaction application, and B-tree freeblock/freelist clusters.
- Dependency closure: no new support component needed; this reuses the lane-local SELECT parser, dynamic JSON table rows, row-array JOIN executor, JSONB validation, and WordPress smoke fixtures.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionTest.php

php -l lanes/libsqlite/examples/wordpress-select-sql-json-dynamic-join.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-select-sql-json-dynamic-join.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionTest.php
Focused test run: 1 selected test files (root lock skipped)
26 PASS lines
1 test files, 42 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-select-sql-json-dynamic-join.php
prints the stable `wordpress-select-sql-json-dynamic-join` scenario with priority, left-join, and nested-left-join rows.
```
