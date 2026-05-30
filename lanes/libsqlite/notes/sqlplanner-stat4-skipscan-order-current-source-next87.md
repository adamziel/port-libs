# SQL Planner STAT4 Skip-Scan ORDER Current Source Next87

Adds `SQLiteStat4SkipScanOrderCurrentSourcePlan`, a bounded current-source
wrapper around the existing STAT4 partial skip-scan ORDER planner. It compares
a prepared source against the current schema/stat4 source, forces reprepare
when the schema cookie or STAT4 generation changes, and reports rowid,
estimate, ORDER mode, and per-prefix STAT4 current/next deltas from the
selected current source.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteStat4SkipScanOrderCurrentSourceNext87Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 43 assertions, 0 failures

php lanes/libsqlite/examples/application-planner-stat4-skipscan-order-current-source-next87.php --self-test
application-planner-stat4-skipscan-order-current-source-next87 self-test passed
```

Dependency closure: no new support component is needed. This reuses the native
PHP `SQLiteSkipScanStat4PartialOrderPlan`, partial-index predicate proof, and
STAT4 sample evidence helpers.

Non-overlap: this avoids accepted multicolumn skip-scan admission, STAT4
partial skip-scan ORDER current/next52, STAT4 JSON/expression covering order,
expression-index range costs, SELECT expression ORDER BY, JSON table
source/cursor/constraint work, B-tree page/freelist/overflow clusters, and
WAL/VFS transaction clusters. The new behavior is current-source freshness and
reprepare selection for STAT4 skip-scan ORDER plans.
