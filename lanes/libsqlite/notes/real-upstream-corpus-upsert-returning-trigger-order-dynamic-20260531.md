real-upstream-corpus-upsert-returning-trigger-order-dynamic-20260531

Slice:
- real-upstream-corpus-upsert-returning-dynamic-20260531T062137Z-0

Accepted base:
- 68a3731675769814ce7d56857d9182ac7f8b3613

Upstream source truth:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test
- /home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test

Ported upstream scenarios:
- upsert2.test 300: ON CONFLICT DO UPDATE fires BEFORE INSERT, BEFORE UPDATE, and AFTER UPDATE triggers.
- upsert2.test 320: failed DO UPDATE WHERE fires only BEFORE INSERT.
- upsert2.test 400/420: same trigger ordering applies to WITHOUT ROWID tables.
- returning1.test 17: RETURNING emits one row per successful insert/update in statement order.

Patch:
- Added SQLiteUpsertReturningTriggerOrderPlan as a bounded native PHP trigger-order executor for generic UPSERT RETURNING rows.
- Added SQLiteRealUpstreamUpsertReturningTriggerOrderDynamicTest with 1000 dynamic variants plus source/dependency/error checks.

Focused verification:
- php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerOrderDynamicTest.php
- Result: 1 test files, 6004 assertions, 0 failures.
- PASS cases: 1004 focused TestRunner PASS lines in the file.

Non-overlap:
- Existing accepted files already cover SELECT-input alias UPSERT RETURNING, omitted-target row streams, excluded aliasing, expression assignments, and broad returning rows.
- This batch isolates trigger firing order around UPSERT conflict decisions and RETURNING row streams.

Dependency closure:
- No external support component is needed.
- The patch adds the smallest lane-local native PHP support component for this upstream behavior cluster.
