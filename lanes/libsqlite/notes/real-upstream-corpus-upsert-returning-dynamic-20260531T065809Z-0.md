# real-upstream-corpus-upsert-returning-dynamic-20260531T065809Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- Ported sections:
  - `upsert5.test` `1.*.400` through `1.*.405`: targeted `ON CONFLICT(...) DO UPDATE` arms take precedence over later catch-all `ON CONFLICT DO UPDATE` arms.
  - `upsert5.test` `1.*.410` through `1.*.413`: catch-all `ON CONFLICT DO UPDATE` handles any unresolved uniqueness conflict.
  - `upsert5.test` `1.*.420` through `1.*.505`: targeted `DO NOTHING` short-circuits later catch-all arms and suppresses `RETURNING` rows.

## Local Coverage

- Added `SQLiteRealUpstreamUpsertReturningDynamicCatchallCorpusTest.php`.
- The test covers 3 upstream schema-layout classes, all 24 conflict-arm orderings, and 15 non-empty conflict masks.
- Focused assertions: 15,265.
- Focused PASS lines: 6,481.

## Non-Overlap

This slice does not repeat accepted broad `upsert5` first-arm ordering, excluded-alias, no-target conflict, composite-tail, secondary-conflict, target-WHERE, trigger/FK `RETURNING`, row-value `RETURNING`, or autoincrement UPSERT batches. It isolates catch-all arm precedence and `DO NOTHING` `RETURNING` suppression from `upsert5.test` sections `400` through `505`.

## Dependency Closure

No new support component is needed. The batch reuses the existing native PHP `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` and `returningRows()` behavior against hydrated upstream SQLite scenarios.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCatchallCorpusTest.php`
  - `1 test files, 15265 assertions, 0 failures`
