# real-upstream-corpus-upsert-returning-dynamic-20260531T012217Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-100`, `upsert2-200`, `upsert2-201`, `upsert2-320`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `upsert5-1.*` conflict-arm/yield behavior
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-17` multi-row UPSERT RETURNING stream

## Handoff

Added `SQLiteRealUpstreamUpsertReturningDynamicYieldMatrixExtendedTest.php`.
The test ports a generic application settings matrix with 5 upstream-derived
WHERE/conflict gates and 25 source-row streams. Each matrix entry compares the
native `SQLiteUpsertDoUpdateWherePlan` result against an in-memory SQLite
oracle for final row images, RETURNING streams, and `changes()`, then checks
native yield tracing and conflict-arm metadata.

Focused movement:

- `1002` distinct TestRunner PASS cases.
- `2357` focused assertions.
- Non-overlap: extends the accepted UPSERT/RETURNING dynamic family with a
  broader key-name conflict/yield matrix. It does not repeat target-first,
  trigger-old-value, upsert5 full-matrix, redundant-conflict, or existing
  row-value/savepoint tests.

Dependency closure: no new support component needed; the slice reuses the
existing native UPSERT conflict-arm/yield executor and PDO SQLite only as a
local oracle inside focused tests.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldMatrixExtendedTest.php`
  - `1 test files, 2357 assertions, 0 failures`
