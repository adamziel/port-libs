# Real Upstream Corpus: UPSERT RETURNING Dynamic

## Scope

- Base accepted HEAD: `72e7cdb1ae891bd4c5cdf5658524a5a35974f525`.
- Added `SQLiteRealUpstreamUpsertReturningDynamicCorpusTest.php`.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
    - `upsert1-320` partial-index-style conflict gating.
    - `upsert1-400` count-changes/multi-row `DO UPDATE` behavior.
    - `upsert1-500` `INSERT ... SELECT ... WHERE true` conflict flow.
    - `upsert1-700` targeted conflict arm ordering.
    - `upsert1-800` expression-index-equivalent value comparison with SQL NULL behavior.
    - `upsert1-1100`, `upsert1-1200`, and `upsert1-1300` regression-shaped conflict gates.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
    - `returning1-1.0` through `returning1-1.8` INSERT RETURNING row images.
    - `returning1-2.1`, `returning1-3.1`, and `returning1-4.2`/`4.5` UPDATE/DELETE/UPSERT RETURNING row images.
    - `returning1-6.0`, `returning1-7.2`, `returning1-7.6`, and `returning1-7.8` projection and invalid qualifier behavior.

## Evidence

- Focused assertion count: `502` assertions.
- Focused command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorpusTest.php`
  - Result: `1 test files, 502 assertions, 0 failures`.
- Syntax check:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorpusTest.php`
  - Result: no syntax errors.

## Non-Overlap

This batch is not the prior static UPSERT/RETURNING helper coverage. It uses the hydrated upstream SQLite corpus names from `upsert1.test` and `returning1.test`, generic `app_settings` rows, PDO SQLite oracle comparison for dynamic `INSERT ... ON CONFLICT ... DO UPDATE ... RETURNING` outcomes, and the port's `SQLiteUpsertDoUpdateWherePlan` plus `returningRows()` behavior.

It does not claim new mapped denominator rows. Expected public movement is focused PASS/assertion growth only: `phpPass` estimate `188568 -> 189070`.

## Dependency Closure

No new native support component is needed. The focused test reuses the existing `SQLiteUpsertDoUpdateWherePlan` and PDO SQLite as a local oracle only; the asserted behavior remains in the libsqlite port helper.

## Follow-Up

Next non-overlapping corpus work should target `upsert2.test`/`upsert3.test` conflict target parsing and chained arms, or deeper `returning1.test` trigger/view cases that require trigger execution behavior rather than row-array projection.
