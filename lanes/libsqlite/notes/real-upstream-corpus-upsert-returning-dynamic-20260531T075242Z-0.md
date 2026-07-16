# real-upstream-corpus-upsert-returning-dynamic-20260531T075242Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-9.0`: creates `v`, `hist`, and an AFTER INSERT trigger whose body upserts `hist(x,cnt)`.
  - `upsert4-9.1`: inserting repeated source values into `v` increments histogram counts through the trigger UPSERT body.

## PHP coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTriggerHistogramTest.php`.
- The file runs 1000 deterministic dynamic source streams through `SQLiteUpsertReturningSql`.
- Each dynamic case checks the per-trigger UPSERT `RETURNING` stream, final ordered histogram rows, inserted key count, updated key count, total change count, max repeated-key count, and ordered key set.
- Focused result: `1 test files, 7003 assertions, 0 failures`.
- Expected focused PASS-line growth: `1002` TestRunner PASS cases.

## Non-overlap

- This does not repeat accepted `upsert4` sections 1, 6, 7, or 8; `upsert2` SELECT-input and alias behavior; `upsert5` arm-priority matrices; excluded-alias handling; omitted-target DO NOTHING; or `returning1` duplicate-row stream behavior.
- This slice owns the remaining `upsert4.test` trigger-body histogram behavior and adds dynamic `RETURNING` checks using generic `hist`/source stream names.

## Dependency closure

- No new support component is needed.
- The slice reuses the existing native `SQLiteUpsertReturningSql` executor, `SQLiteUpsertDoUpdateWherePlan` row-array conflict application, and native `RETURNING` projection.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTriggerHistogramTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTriggerHistogramTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
