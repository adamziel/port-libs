# real-upstream-corpus-upsert-returning-dynamic-20260530T193932Z-0

Status: ready.

Accepted base inspected: `bc1638b6eb86853297e97bc15107a4f4f8e9ef19`.

Implemented behavior:

- Extended `SQLiteUpsertReturningSql` with a bounded parser/executor path for `WITH nx(...) AS (VALUES ...) INSERT INTO ... SELECT ... FROM nx WHERE true ON CONFLICT ... RETURNING ...`.
- Preserved existing `VALUES` input behavior and existing aliased RETURNING expression rules.
- Added focused real-upstream tests for SELECT-source UPSERT/RETURNING behavior from:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`: `upsert2-200`, `upsert2-201`, `upsert2-210`, plus `upsert2-100` failed-WHERE semantics.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`: `upsert1-101` DO NOTHING conflict skipping.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`: `returning1-4.5` mixed inserted/updated RETURNING row order and `returning1-17` duplicate UPSERT RETURNING rows.

Focused growth:

- New focused file: `SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php`.
- New focused TestRunner PASS cases/assertions: 69.
- Related UPSERT RETURNING family check: 6 files / 897 assertions / 0 failures.

Non-overlap:

- This does not repeat the existing lower-level row-array coverage for `upsert2-200`/`upsert2-201`/`upsert2-210`; the new behavior is parser-level SELECT input admission in `SQLiteUpsertReturningSql`.
- This does not claim mapped denominator movement and does not add generated fake upstream script ids.

Dependency closure:

- No new support component needed. The patch reuses the existing bounded SQL UPSERT RETURNING executor and adds a narrow CTE `VALUES`/`SELECT` input parser needed for the cited upstream cases.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php`: 1 test files / 69 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpsertDoNothingReturningCurrentTest.php lanes/libsqlite/tests/SQLiteUpsertReturningExpressionCurrentNext70Test.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSqlDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectInputDynamicTest.php`: 6 test files / 897 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test files / 3 assertions / 0 failures.
- Root harness: not run - isolated micro-slice.
