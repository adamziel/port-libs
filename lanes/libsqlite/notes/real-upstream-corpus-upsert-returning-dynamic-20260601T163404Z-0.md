# Real Upstream Corpus: UPSERT RETURNING WITHOUT ROWID NOT NULL Barrier

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260601T163404Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`, `upsert1-1000`.

Implemented behavior:

- Added `SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort()` to model the upstream `WITHOUT ROWID` table rule that a missing primary-key value fails with `NOT NULL constraint failed` before the `ON CONFLICT(c2) DO UPDATE` arm can run.
- Added `SQLiteRealUpstreamUpsertReturningWithoutRowidNotNullDynamicTest.php` with 1000 deterministic dynamic cases plus source, malformed-input, non-overlap, and dependency-closure checks.
- The dynamic cases assert unchanged row storage, zero changes, no inserted/updated/skipped rows, no RETURNING rows, no conflict probe, and no later-row processing after the first incoming row misses the `WITHOUT ROWID` primary key.

Non-overlap:

- This owns the `upsert1-1000` primary-key NOT NULL barrier. It avoids the accepted secondary unique-conflict abort, target-priority matrix, OR IGNORE `WITHOUT ROWID` duplicate suppression, trigger-order streams, upsert5 arm-priority matrix, QRF formatter, and RETURNING trigger old-image coverage.

Dependency closure:

- No new external support component is needed. The slice adds a bounded native PHP row-array barrier for `WITHOUT ROWID` primary-key validation before UPSERT conflict handling and RETURNING row emission.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertWithoutRowidConstraintPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningWithoutRowidNotNullDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningWithoutRowidNotNullDynamicTest.php` passed: `1 test files, 24014 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 7 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
