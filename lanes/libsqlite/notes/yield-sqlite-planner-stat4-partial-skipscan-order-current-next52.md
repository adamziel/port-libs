# STAT4 Partial Skip-Scan ORDER Current/Next 52

This slice extends `SQLiteSkipScanStat4PartialOrderPlan` with per-prefix
STAT4 current/next sample evidence for partial skip-scan range loops and
direction-aware ORDER BY classification. It keeps the accepted current-next36
partial suffix ORDER BY mode stable while adding reverse-scan, mixed-direction,
sort-block count, and per-loop sample-boundary diagnostics.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialSkipScanOrderCurrentNext52Test.php
Focused test run: 1 selected test files (root lock skipped)
55 PASS lines / 55 assertions / 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSkipScanStat4PartialOrderCurrentNext36Test.php lanes/libsqlite/tests/SQLitePlannerStat4PartialSkipScanOrderCurrentNext52Test.php
Focused test run: 2 selected test files (root lock skipped)
124 assertions / 0 failures

php lanes/libsqlite/examples/application-planner-stat4-partial-skipscan-order-current-next52.php --self-test
application-planner-stat4-partial-skipscan-order-current-next52 self-test passed
```

New dashboard-visible focused PASS delta: `+55` verified PHP PASS lines from
the new current-next52 test file, raising lane-local `phpPass` from `19277` to
`19332`.

Dependency closure: no new support component is needed. This reuses existing
native PHP skip-scan, partial-index predicate proof, STAT4 sample metadata, and
Application copied-row planner fixtures.

Non-overlap: this avoids accepted batch49 STAT4 partial-expression ORDER
planning, accepted current-next36 skip-scan STAT4 partial ORDER basics,
partial-index WHERE implication planning, expression-index range costs,
SELECT expression ORDER BY, JSON table sources/constraints, B-tree page
relocation/root collapse/overflow release, and WAL/VFS transaction clusters.
The new behavior is per-prefix STAT4 current/next evidence plus
direction-aware partial skip-scan ORDER diagnostics.
