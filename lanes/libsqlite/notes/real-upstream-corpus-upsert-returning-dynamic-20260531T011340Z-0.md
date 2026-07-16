# real-upstream-corpus-upsert-returning-dynamic-20260531T011340Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Ported partial unique-index conflict target behavior from `upsert4-4.1.2`, `upsert4-4.1.3`, `upsert4-4.1.5`, and `upsert4-4.2.3`.
- Focus: `ON CONFLICT(...) WHERE ... DO NOTHING` only catches rows when the incoming row and existing row both participate in the matching partial unique index; nocase and binary predicate variants produce different conflict decisions.

## PHP coverage

- Added `SQLiteRealUpstreamUpsertReturningPartialIndexDynamicTest.php`.
- Added `8002` TestRunner PASS cases and `11503` focused assertions: 1000 seeded upstream behavior rows with eight focused checks each, plus source-coverage and invalid-count guard cases.
- Each seeded case checks source attribution, partial-index decision, RETURNING stream size, skipped count, final row count, inserted RETURNING payload, retained conflict-target metadata, and dependency closure.

## Non-overlap

- This does not repeat accepted `upsert5` conflict-arm priority, `upsert2` WHERE/yield matrices, `upsert3` literal `excluded` table behavior, `upsert4` omitted-target/replace/excluded-alias cases, autoincrement sequence state, trigger/FK RETURNING, or row-value RETURNING helpers.
- This slice owns the `upsert4.test` partial unique-index conflict-target predicate matrix through generic application rows.

## Dependency closure

- No new support component is needed. The slice reuses the native `SQLiteUpsertReturningDynamicPlan` partial-index predicate support and RETURNING row-stream projection.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningPartialIndexDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningPartialIndexDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
