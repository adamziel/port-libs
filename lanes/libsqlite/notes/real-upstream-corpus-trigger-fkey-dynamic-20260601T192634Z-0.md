# real-upstream-corpus-trigger-fkey-dynamic-20260601T192634Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Upstream scenarios: `fkey2-7.1` through `fkey2-7.9`
- Behavior cluster: `INTEGER PRIMARY KEY` child column may be the FK child key, and updates to that child key or its `rowid` alias must run the same parent-key check as ordinary child-key updates.

Implemented coverage:

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2IntegerPrimaryKeyChildPlan()` for fkey2-7 statement behavior.
- Models `INSERT INTO t2`, `UPDATE t2 SET c`, `DELETE FROM t1`, `UPDATE t1 SET a`, and `UPDATE t2 SET rowid`.
- Preserves statement rollback on FK failure and exposes the `rowid` alias as the same value as child key `c`.
- Rejects malformed child rows where an explicit `rowid` disagrees with `c`.

Focused evidence:

- Red-first check before source change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicIntegerPrimaryKeyChild20260601Test.php`
  - Result: failed on missing `SQLiteDynamicTriggerForeignKeyPlan::fkey2IntegerPrimaryKeyChildPlan()` after 2 source-citation PASS lines.
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicIntegerPrimaryKeyChild20260601Test.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicIntegerPrimaryKeyChild20260601Test.php`
  - Result: `1 test files, 15130 assertions, 0 failures`.
  - PASS-line delta: `14405`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyBlobColumnDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicPragmaToggleCorpusTest.php`
  - Result: `2 test files, 19612 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicGraphCascade20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDefinitionDiagnosticTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCountChangesBoundaryTest.php`
  - Result: `3 test files, 18226 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 8 assertions, 0 failures`.

Non-overlap:

- Covers only upstream `fkey2.test` fkey2-7 integer-primary-key child FK and rowid-alias checks.
- Does not cover or duplicate fkey2-5 incremental blob FK-column guard, fkey2-8 PRAGMA `foreign_keys` transaction toggles, fkey2-9 `SET DEFAULT`, fkey2-12 action matrix, fkey2-17 `count_changes`, fkey2-20 conflict policy, temp triggers, trigger recursion, or drop-trigger cleanup.

Dependency closure:

- No new support component is needed.
- Reuses the existing lane-local `SQLiteDynamicTriggerForeignKeyPlan` model against hydrated upstream `fkey2.test` source truth.

Next task:

- Continue with a different real upstream trigger/FK gap, preferably one that exercises executor/runtime behavior not already covered by fkey2-5, fkey2-7, fkey2-8, fkey2-12, fkey2-17, fkey2-20, temptrigger, triggerupfrom, or recursive trigger families.
