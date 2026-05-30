# Real upstream UPSERT/RETURNING dynamic batch

Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T202548Z-0`
Accepted base: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`

Added `SQLiteRealUpstreamCorpusUpsert2ReturningDynamicBatchTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Covered upstream scenarios:

- `upsert2-100` and `upsert2-110`: VALUES-source UPSERT updates/inserts/skips through `WHERE t1.b < excluded.b`.
- `upsert2-200` and `upsert2-201`: repeated CTE-style source conflicts see the statement-current row and alias-qualified target references.
- `upsert2-300`, `upsert2-310`, `upsert2-320`, `upsert2-400`, `upsert2-410`, `upsert2-420`, `upsert2-421`: trigger firing shape for DO UPDATE, DO NOTHING, and failed DO UPDATE WHERE paths.
- `returning1.test`: RETURNING rows are produced only for changed UPSERT rows and preserve statement order.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsert2ReturningDynamicBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsert2ReturningDynamicBatchTest.php`
  - Result: `1 test files, 1120 assertions, 0 failures`
  - PASS-case growth: `+1088` focused TestRunner cases

Non-overlap:

- This does not edit the already accepted `SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php` cluster.
- The new batch targets upstream `upsert2.test` statement-current rows, alias-qualified updates, trigger traces, DO NOTHING, and RETURNING stream parity using PDO SQLite as the oracle for each SQL shape.
- It does not add generated fake upstream script ids or metadata-only admissions.

Dependency closure:

- No new support component is needed. Existing `SQLiteUpsertDoUpdateWherePlan` and `executeWithTriggerTrace()` behavior is reused.
