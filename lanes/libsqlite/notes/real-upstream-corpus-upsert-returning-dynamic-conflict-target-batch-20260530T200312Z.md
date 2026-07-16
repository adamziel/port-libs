# Real Upstream Corpus: UPSERT RETURNING Dynamic Conflict Target Batch

Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T200312Z-0`

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-100` through `upsert1-140`: primary-key and unique-index conflict target matching/rejection.
  - `upsert1-200` through `upsert1-320`: expression and partial unique-index conflict target behavior.
  - `upsert1-700` through `upsert1-800`: first matching uniqueness constraint priority when several constraints conflict.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-4.2` and `returning1-4.5`: UPSERT `DO UPDATE` plus `RETURNING` row images.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `upsert5-420` and `upsert5-500`: `DO NOTHING` conflict arms suppress RETURNING rows.

Lane-local changes:

- Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicConflictTargetBatchTest.php`.
- Reused existing `SQLiteUpsertDoUpdateWherePlan` behavior; no new production API or support dependency was needed.
- The test uses generic `app_settings`-style setting/key/tenant/load-policy terms only.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicConflictTargetBatchTest.php`
  - `1 test files, 12151 assertions, 0 failures`
  - 1951 focused PASS lines

Non-overlap:

- This batch does not repeat accepted UPSERT correlated-delete, schema variant, full-matrix, priority-matrix, redundant-conflict, tail, or yield files. It focuses on `upsert1.test` conflict target validation/priority and `returning1.test` updated row images in a fresh current-base file.

Dependency closure:

- No new support component is needed. The existing bounded row-array UPSERT/RETURNING helper was sufficient for this real upstream behavior cluster.
