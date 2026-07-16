# real-upstream-corpus-trigger-fkey-dynamic-20260531T042313Z-0

Status: ready for integration from accepted base `5823f556f77d50bd49ce909acb22097fc44da229`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-15.1.1..15.1.7`: unnecessary FK search/found scans are elided while the deferred constraint counter is zero, and re-enabled while violations are pending or rollback must unwind pending checks.
- `fkey2-16.1.*`: self-referential foreign-key rows can be inserted, updated when the key/reference remain consistent, deleted, and reject orphaning updates/inserts.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCounterScanPlan()` for the `fkey2-15.*` scan-counter behavior.
- Added `SQLiteDynamicTriggerForeignKeyPlan::selfReferentialForeignKeyPlan()` for the `fkey2-16.*` self-reference row behavior.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfRef20260531Test.php` with 4,358 focused assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfRef20260531Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfRef20260531Test.php` passed: `1 test files, 4358 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCounterSelfRef20260531Test.php` passed: `2 test files, 5707 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `2025275 -> 2029633` if this focused real-upstream test file is accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; mapped inventory is already complete.

Non-overlap:

- Does not repeat accepted trigger/FK DDL `fkey2-14.*`, conflict policy `fkey2-20.*`, count_changes `fkey2-17.*`, action-journal `fkey8.*`, quoted cascade `fkey1.*`, trigger RAISE, triggerF conflict-delete, or recursive trigger/view/upsert-returning coverage.
- This slice covers only `fkey2-15.*` scan-counter elision and `fkey2-16.*` self-referential FK consistency.

Dependency closure:

- No new support component is needed. The slice reuses lane-local trigger/FK planning helpers and existing identifier/row matching utilities.
