# SQLite JSON each indexed regression next12

- Micro-slice: `yield-sqlite-json-each-indexed-regression-next12`.
- Behavior: parser-level comma-form FROM sources now lower to CROSS JOIN row production, allowing upstream-style `FROM user, json_each(user.phone)` and `json_tree(row.json, row.root)` dynamic virtual-table scans to execute through the existing JSON table planner.
- Focused test delta: `+26` TestRunner PASS cases in `SQLiteJsonEachIndexedRegressionNext12Test.php`.
- Expected `phpPass` delta: `+26`, from `3796` to `3822`.
- `benchmarkDenominator.mapped` unchanged; this is focused PHP executor coverage inspired by upstream JSON comma-join regression shape, not a fresh hydrated upstream runner mapping.
- Non-overlap: avoids accepted JSON table cursor/source hidden/visible constraint work, commuted hidden constraints, JSON host joins, expression ORDER BY, SELECT subqueries, Unicode GLOB, VFS/WAL transaction application, and B-tree freeblock/freelist clusters.
- Dependency closure: no new support component needed; this reuses the lane-local SELECT parser, dynamic JSON table rows, row-array JOIN executor, JSONB validation, and WordPress smoke fixtures.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionNext12Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionNext12Test.php

php -l lanes/libsqlite/examples/wordpress-json-each-comma-join.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-json-each-comma-join.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionNext12Test.php
Focused test run: 1 selected test files (root lock skipped)
26 PASS lines
1 test files, 42 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-json-each-comma-join.php
prints top priority `site_plugin_settings` / `7`, `priority_count` 4, and three flattened flags.
```
