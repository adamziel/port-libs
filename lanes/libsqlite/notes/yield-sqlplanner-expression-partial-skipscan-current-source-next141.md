# SQL Planner Expression Partial Skipscan Current Source Next141

This slice adds `SQLiteSkipScanStat4PartialOrderPlan::expressionPartialSkipScanCurrentSourceNext141()`.
It reuses the accepted expression skip-scan materializer, then records a
partial-predicate current-source fence and rejects a next source whose
expression keys, schema cookie, or STAT4 samples changed before the prepared
cursor can be reused.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionPartialSkipScanCurrentSourceNext141Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 67 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-planner-expression-partial-skipscan-current-source-next141.php --self-test
application-planner-expression-partial-skipscan-current-source-next141 self-test passed
```

Expected dashboard movement: `phpPass` +67 from the 67 independent PASS lines
in `SQLitePlannerExpressionPartialSkipScanCurrentSourceNext141Test.php`.
Mapped coverage remains conservative at `606 / 1589`; this is focused PHP
planner behavior over existing partial-index/skip-scan inventory, not a newly
mapped upstream manifest row.

Non-overlap: avoids accepted next129 expression skip-scan materialization,
next132 expression-covering rows, next136 partial range covering, STAT4
partial expression planning, expression ORDER BY, range-cost ranking, JSON,
VFS, WAL, and B-tree accepted surfaces. The new behavior is current-source
partial-predicate source fencing and explicit next-source rejection for stale
expression skip-scan cursors.

Dependency closure: no new support component is needed. The slice composes
existing native PHP expression-key materialization, partial-index implication,
STAT4 skip-scan estimates, and current-source fences.

Root harness: not run - isolated micro-slice.
