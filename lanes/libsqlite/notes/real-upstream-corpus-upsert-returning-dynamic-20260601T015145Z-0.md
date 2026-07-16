# real-upstream-corpus-upsert-returning-dynamic-20260601T015145Z-0

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/nulls1.test`
- `nulls1-3.1.11`: `ON CONFLICT (b DESC NULLS LAST)` is rejected with
  `unsupported use of NULLS LAST`.
- `nulls1-3.1.12`: the same unsupported conflict-target modifier is rejected
  inside a trigger body with `NULLS FIRST`.

## Patch

- `SQLiteUpsertReturningSql` now reports SQLite's unsupported-NULLS diagnostic
  while parsing UPSERT conflict targets, instead of the previous generic
  malformed-target error.
- `SQLiteUpsertDoUpdateWherePlan::admitConflictTarget()` applies the same
  diagnostic to planner/admission target terms.
- Added `SQLiteRealUpstreamUpsertReturningNullsConflictTargetDynamicTest.php`
  with 1000 deterministic variants covering SQL execution preflight,
  planner target admission, and a valid control path that still updates and
  returns the current row image.

## Evidence

- Red check before the fix:
  `SQLiteUpsertReturningSql::execute(... ON CONFLICT (b DESC NULLS LAST) ...)`
  threw `SQLite UPSERT RETURNING conflict target is malformed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNullsConflictTargetDynamicTest.php`
  passed `1 test files / 3004 assertions / 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNullsConflictTargetDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTargetAdmissionDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4ConflictTargetDynamicTest.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php`
  passed `4 test files / 10920 assertions / 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed `1 test files / 3 assertions / 0 failures`.

## Non-overlap

This owns unsupported `NULLS FIRST|LAST` modifiers in UPSERT conflict targets.
It avoids accepted ORDER BY NULLS sorting, expression ORDER BY, general target
admission, UPSERT row-stream, trigger old-image, and returning schema/virtual
table batches.

## Dependency closure

No new support component is needed. The slice reuses lane-local UPSERT SQL
parsing and conflict-target admission helpers.
