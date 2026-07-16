# real-upstream-corpus-upsert-returning-dynamic-20260531T071308Z-0

Added a focused real upstream UPSERT/RETURNING corpus batch from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
`upsert1-1300`, with RETURNING stream coverage tied to
`returning1.test` `4.1` through `4.5`.

The batch covers the 2024 upstream regression where duplicate
`INSERT ... SELECT ... ON CONFLICT DO UPDATE` source rows must feed a BEFORE
UPDATE trigger the previous target row image as `old` and the UPSERT-updated
image as `new`. It adds 3,001 distinct focused TestRunner PASS cases and 9,002
assertions in
`lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTriggerOldImage20260531Test.php`.

Non-overlap: existing accepted UPSERT dynamic batches cover excluded-alias,
target priority, partial-index, catch-all, statement-current subquery,
conflict-target, and broad yield-trace matrices. This slice owns the
`upsert1-1300` duplicate-source trigger old/new row-image behavior plus changed
row RETURNING order.

Dependency closure: no new support component is needed. The test reuses the
native PHP `SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace()` UPSERT
trigger trace and RETURNING row stream helpers.

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTriggerOldImage20260531Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTriggerOldImage20260531Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTriggerOldImage20260531Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 9002 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```
