# Real Upstream Corpus: UPSERT RETURNING Dynamic Follow-Up

## Scope

- Base accepted HEAD: `77aaee93e1232164eda546b44d6f0e2ddd146261`.
- Added `SQLiteRealUpstreamUpsertReturningDynamicFollowupTest.php`.
- Updated `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` to reject explicit conflict-arm targets that do not match any declared unique constraint, while still accepting reordered composite targets.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
    - `upsert2-100`, `upsert2-110`, `upsert2-200`, `upsert2-201`, and `upsert2-210` for VALUES/SELECT-source UPSERT, repeated same-statement conflicts, skipped conflicts, and changed-row RETURNING order.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
    - `upsert3-110` and `upsert3-120` invalid partial conflict targets.
    - `upsert3-130` and `upsert3-140` composite conflict target admission, including reordered target columns.
    - `upsert3-200` and `upsert3-210` composite conflict updates over a table named `excluded` and aliased-target WHERE behavior.

## Evidence

- Focused new assertion count: `597` assertions.
- Focused command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicFollowupTest.php`
  - Result: `1 test files, 597 assertions, 0 failures`.
- Adjacent command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `4 test files, 2166 assertions, 0 failures`.
- Syntax checks:
  - `php -l lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicFollowupTest.php`
  - Result: no syntax errors.

## Non-Overlap

This batch follows the prior `upsert1.test`, `upsert5.test`, and early `returning1.test` batches without repeating them. It targets `upsert2.test` SELECT-source/repeated-conflict behavior and `upsert3.test` composite conflict-target validation. It does not claim new mapped denominator rows. Expected public movement is focused PASS/assertion growth only: `phpPass` estimate `195948 -> 196545`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP UPSERT conflict-arm executor and tightens its conflict-target validation to match SQLite behavior for explicit targets.

## Follow-Up

Next non-overlapping UPSERT/RETURNING corpus work should target deeper `returning1.test` trigger/view cases or `upsert4.test`/fault-adjacent behavior rather than `upsert2.test` and `upsert3.test` conflict-target coverage.
